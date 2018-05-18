<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\ReFactory;
use ErrorException;
use RuntimeException;
use Throwable;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Control\Authentication\IAuthenticator;
use Datahouse\Elements\Control\Exceptions\ResourceNotModified;
use Datahouse\Elements\Control\Exceptions\RouterException;
use Datahouse\Elements\Control\Session\ISessionHandler;
use Datahouse\Elements\Control\TextSearch\ElasticsearchInterface;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Presentation\IRenderer;
use Datahouse\Elements\Tools\ExceptionLogger;

/**
 * The essential controller for standard HTTP requests, taking care of
 * parsing the request, the users session and sending the response back to
 * the client.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class HttpRequestHandler extends BaseRequestHandler
{
    /** @var IUrlResolver $resolver */
    protected $resolver;
    protected $router;

    /**
     * @param ReFactory              $refactory      class factory
     * @param IStorageAdapter        $adapter        linked storage adapter
     * @param IChangeProcess         $cproc          definition of the change
     *                                               process
     * @param IAuthenticator         $authenticator  for user authentication
     * @param AssetHandler           $assetHandler   for css and js
     * @param ISessionHandler        $sessionHandler taking care of sessions
     * @param ContentCollector       $collector      content collector
     * @param ElasticsearchInterface $esInterface    for full text search
     * @param IUrlResolver           $resolver       url resolver to apply
     * @param BaseRouter             $router         by now you should be able
     *                                               to guess what this guy
     *                                               does
     */
    public function __construct(
        ReFactory $refactory,
        IStorageAdapter $adapter,
        IChangeProcess $cproc,
        IAuthenticator $authenticator,
        AssetHandler $assetHandler,
        ISessionHandler $sessionHandler,
        ContentCollector $collector,
        ElasticsearchInterface $esInterface,
        IUrlResolver $resolver,
        BaseRouter $router
    ) {
        parent::__construct(
            $refactory,
            $adapter,
            $cproc,
            $authenticator,
            $assetHandler,
            $sessionHandler,
            $collector,
            $esInterface
        );
        $this->resolver = $resolver;
        $this->router = $router;
    }

    /**
     * Splits the language preferences as usually given in the HTTP/1.1
     * Accpted-Languages header field.
     *
     * @param string $input usually from the request header
     * @return array of tuples with (string $lang, string $quality)
     */
    public static function parseAcceptedLanguages($input)
    {
        // short-circuit for null input
        if (is_null($input)) {
            return [];
        }
        $filteredParts = array_filter(
            explode(',', $input),
            function ($v) {
                return strlen($v) > 0;
            }
        );
        $arrayOfPairs = array_map(function ($v) {
            if (strpos($v, ';') !== false) {
                list($v, $rest) = explode(';', $v, 2);
                if (preg_match('/q=([\d\.]+)/', $rest, $matches) === 1) {
                    return [trim($v), trim($matches[1])];
                } else {
                    return [trim($v), '1.0'];
                }
            } else {
                // As per RFC 2616, quality defaults to 1.0. Note that we're
                // using strings here for easier testing. However, this also
                // means we don't catch invalid values.
                return [trim($v), "1.0"];
            }
        }, $filteredParts);
        return array_combine(
            // keys
            array_map(function ($v) {
                return $v[0];
            }, $arrayOfPairs),
            // values
            array_map(function ($v) {
                return $v[1];
            }, $arrayOfPairs)
        );
    }

    /**
     * Known HTTP status codes according to RFC 2616, unless otherwise noted.
     */
    const HTTP_STATUS_DESCRIPTIONS = [
        100 => "Continue",
        101 => "Switching Protocols",
        102 => "Processing",  // RFC 2518

        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",  // RFC 7233
        207 => "Multi-Status",  // RFC 4918
        208 => "Already Reported",  // RFC 5842
        226 => "IM Used",  // RFC 3229

        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",  // since HTTP 1.1
        304 => "Not Modified",
        305 => "Use Proxy",  // since HTTP 1.1
        // 306 is no longer used
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",  // RFC 7538

        400 => "Bad Request",
        401 => "Unauthorized",  // RFC 7235
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",  // RFC 7235
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",  // RFC 7232
        413 => "Request Entity Too Large",  // RFC 7231
        414 => "Request-URI Too Long",  // RFC 7231
        415 => "Unsupported Media Type",
        416 => "Requested Range Not Satisfiable",  // RFC 7233
        417 => "Expectation Failed",
        418 => "I'm a teapot",  // RFC 2324
        421 => "Misdirected Request",  // RFC 7540
        422 => "Unprocessable Entity",  // RFC 4918
        423 => "Locked",  // RFC 4918
        424 => "Failed Dependency",  // RFC 4918
        426 => "Upgrade Required",
        428 => "Precondition Required",  // RFC 6585
        429 => "Too Many Requests",  // RFC 6585
        431 => "Request Header Fields Too Large",  // RFC 6585
        451 => "Unavailable For Legal Reasons",

        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
        506 => "Variant Also Negotiates",  // RFC 2295
        507 => "Insufficient Storage",  // RFC 4918
        508 => "Loop Detected",  // RFC 5842
        510 => "Not Extended",  // RFC 2774
        511 => "Network Authentication Required",  // RFC 6585
    ];

    /**
     * Given a HTTP response code, this method returns the appropriate
     * description as per RFC 2616, so these are consistent and less repetitive
     * within elements.
     *
     * @param int $code of the http response
     * @return string
     * @throws \RuntimeException for unknown status codes
     */
    public static function getHttpStatusDescription($code)
    {
        if (array_key_exists($code, static::HTTP_STATUS_DESCRIPTIONS)) {
            return static::HTTP_STATUS_DESCRIPTIONS[$code];
        } else {
            throw new \RuntimeException("unknown HTTP status code");
        }
    }

    /**
     * Sends an error response to the client.
     *
     * @param  int    $code    HTTP Status Code
     * @param  string $content content for the body of the error response
     * @return void
     */
    public function sendHttpResponse(int $code, string $content = '')
    {
        $desc = static::getHttpStatusDescription($code);
        header("HTTP/1.1 $code $desc");
        if ($code >= 300 && $code != 304) {
            header("Content-Type: text/plain");
            echo $desc;
            if (strlen($content) > 0) {
                echo ': ' . $content;
            }
        }
    }

    /**
     * Sends the fully processed request to the client and should eventually
     * handle special cases like HEAD or OPTIONS requests.
     *
     * @param int    $responseCode to send in headers (w/o the encoding)
     * @param string $contentType  to send in headers (w/o the encoding)
     * @param string $renderedHtml the HTML code to emit
     * @return void
     */
    public function handleResponse($responseCode, $contentType, $renderedHtml)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        assert($method !== 'OPTIONS');
        if ($method == 'GET' || $method == 'POST' || $method == 'HEAD') {
            if ($method != 'OPTIONS') {
                header('Content-Type: ' . $contentType . '; encoding=utf-8');
                header('Content-Length: ' . strlen($renderedHtml));
            }

            // The initial page view depends on the languages defined in the
            // client's browser, so add a vary header for those to ensure
            // caches differentiate between languages.
            header('Vary: Accept-Language, Accept-Encoding');

            if ($method == 'GET' || $method == 'POST') {
                header('HTTP', true, $responseCode);
                echo $renderedHtml;
            } elseif ($method == 'OPTIONS') {
                header('Allow: GET, HEAD');
                // FIXME: maybe support CORS headers?
            }
        } else {
            $this->sendHttpResponse(405);
        }
    }

    /**
     * Some helpful php config and settings adjustments.
     * @return void
     */
    protected static function sanitizePhp()
    {
        // Sanitize PHP a bit.
        ini_set('display_errors', 0);
        error_reporting(E_ALL);

        // Without this  shutdown handler here, we'd get a 200 Ok in case of
        // a shutdown due to a fatal error. Weird, but true.
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null) {
                header('HTTP/1.1 500 Internal Server Error');

                $errno = $error["type"];
                $errfile = $error["file"];
                $errline = $error["line"];
                $errstr = $error["message"];

                echo("Fatal error no $errno in $errfile:$errline: " . $errstr);
            }
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED])) {
                error_log("WARNING: Use of deprecated code:");
                $bt = array_slice(debug_backtrace(), 2, 1);
                ExceptionLogger::logStackTrace($bt);
            } else {
                throw new ErrorException(
                    $errstr,
                    $errno,
                    0,
                    $errfile,
                    $errline
                );
            }
        });
    }

    /**
     * @return void
     * @throws ConfigurationError
     */
    protected static function checkConfiguration()
    {
        // Check PHP configuration - this may be different between dev and
        // prod, however, only certain combinations are reasonable.
        if (ini_get('assert.active')) {
            if (ini_get('zend.assertions') <= 0) {
                throw new ConfigurationError(
                    "Please set zend.assertions to 1 to enable assert "
                    . "checking or disable assert.active."
                );
            }
            if (ini_get('assert.exception') == false) {
                throw new ConfigurationError("Please enable assert.exception.");
            }
        } else {
            if (ini_get('zend.assertions') > 1) {
                throw new ConfigurationError(
                    "Please set zend.assertions to -1 or at least 0 to disable"
                    . " assert checking or enable assert.active to."
                );
            }
        }

        // Dumping a full stack trace to the client browser isn't ever
        // reasonable.
        if (ini_get('assert.warning')) {
            throw new ConfigurationError("Please disable assert.warning.");
        }
        // And that's plain unnecessary, we want the exception.
        if (ini_get('assert.bail')) {
            throw new ConfigurationError("Please disable assert.bail.");
        }
    }

    /**
     * Reads request headers and possibly changes the session language.
     *
     * @param HttpRequest $request to serve, providing http headers
     * @return string session language or the empty string
     */
    private function determineSessionLanguage(HttpRequest $request) : string
    {
        // If a specific language is set via request parameters, store the
        // choice in the session.
        $explicitChoice = $request->getParameter('language');
        // FIXME: historically, we have two ways to set this...
        if (strlen($explicitChoice) == 0) {
            $explicitChoice = $request->getParameter('edit_language');
        }

        if (strlen($explicitChoice) > 0) {
            $this->sessionHandler->setLanguage($explicitChoice);
            return $explicitChoice;
        } else {
            return $this->sessionHandler->getLanguage();
        }
    }

    /**
     * Handles all language related request processing and initializes the
     * content selector.
     *
     * @param HttpRequest $request to process
     * @return void
     */
    private function handleLanguage(HttpRequest $request)
    {
        $sessionLanguage = $this->determineSessionLanguage($request);
        $langPreferences = static::getLanguagePreferences(
            $request,
            $sessionLanguage
        );
        $this->collector->getContentSelector()->setLanguagePreferences(
            $langPreferences,
            $this->getGlobalOptions()['DEFAULT_LANGUAGE']
        );
    }

    /**
     * Determine the language preferences of the user, taking into account the
     * browser's and the session's choice.
     *
     * @param HttpRequest $request         to serve, providing http headers
     * @param string      $sessionLanguage maybe an empty string
     * @return array
     */
    public static function getLanguagePreferences(
        HttpRequest $request,
        string $sessionLanguage
    ) : array {
        // Add the relevant http header value for language choices, if set.
        if (isset($request->acceptLanguage)) {
            $preferences = static::parseAcceptedLanguages(
                $request->acceptLanguage
            );
        } else {
            $preferences = [];
        }

        // Add the session's language with a very high preference, if set.
        if (strlen($sessionLanguage) > 0) {
            $preferences[$sessionLanguage] = '2.0';
        }

        return $preferences;
    }

    /**
     * @param string      $userId     for which to process the request
     * @param IController $controller to use
     * @param HttpRequest $request    retrieved
     * @return void
     */
    public function processAllowedMethodRequest(
        string $userId,
        IController $controller,
        HttpRequest $request
    ) {
        if (empty($userId)) {
            $user = User::getAnonymousUser();
        } else {
            $user = $this->adapter->loadUser($userId);
            if (is_null($user)) {
                // shouldn't ever happen, but looks like a sane
                // default just in case.
                $user = User::getAnonymousUser();
            }
        }
        /* @var IRenderer $template */
        $response = $controller->processRequest(
            $request,
            $user
        );

        $renderer = $response->getRenderer();
        $res = $renderer->render($this->asset_handler);
        if (is_array($res)) {
            list ($contentType, $content) = $res;
            $this->handleResponse($response->getStatusCode(), $contentType, $content);
        } else {
            assert(is_null($res));
            // AFAICT only these two are allowed to answer with a 204.
            assert(
                $request->method === 'PUT'
                || $request->method === 'DELETE'
            );
            $this->sendHttpResponse(204);
        }
    }

    /**
     * @return void
     * @throws ConfigurationError
     */
    private function handleResourceNotFoundError()
    {
        $globalOptions = $this->getGlobalOptions();
        if (isset($globalOptions['ERROR_404_REDIRECT'])) {
            $request = new HttpRequest();
            $request->url = $globalOptions['ERROR_404_REDIRECT'];
            $request->method = 'GET';

            try {
                $controller = $this->router->startRouting($this, $request);
            } catch (RouterException $e) {
                throw new ConfigurationError(
                    "unable to find configured error 404 page under: "
                    . $request->url
                );
            }

            // Always show the 404 error page as the anonymous user. If
            // an admin user wants to edit the page, she needs the real
            // URL of the page.
            $user = User::getAnonymousUser();
            $response = $controller->processRequest($request, $user);
            $renderer = $response->getRenderer();
            $res = $renderer->render($this->asset_handler);
            if (is_array($res)) {
                list ($contentType, $content) = $res;
                if ($response->getStatusCode() == 200) {
                    $this->handleResponse(404, $contentType, $content);
                } else {
                    throw new RuntimeException("error rendering error page");
                }
            } else {
                assert(is_null($res));
                // AFAICT only these two are allowed to answer with a 204.
                assert(
                    $request->method === 'PUT'
                    || $request->method === 'DELETE'
                );
                $this->sendHttpResponse(204);
            }
        } else {
            // No custom error page configured.
            $this->handleResponse(404, "text/plain", "Not found");
        }
    }

    /**
     * @param HttpRequest $request to process
     * @return void
     * @throws ConfigurationError
     * @throws RuntimeException
     * @throws RouterException
     */
    private function processHttpRequest(HttpRequest $request)
    {
        $this->sessionHandler->initializeSession();

        // Logout the user if she deletes her admin cookie.
        if (!array_key_exists('admin', $_COOKIE) ||
            $_COOKIE['admin'] !== 'true'
        ) {
            $this->sessionHandler->unsetUser();
        }

        $userId = $this->sessionHandler->getUser();

        $this->handleLanguage($request);

        try {
            $controller = $this->router->startRouting($this, $request);
        } catch (ResourceNotFound $e) {
            $this->handleResourceNotFoundError();
            return;
        }

        $allowedMethods = $controller->enumAllowedMethods();

        if ($request->method === 'OPTIONS') {
            header('Vary: Accept-Language');
            $allowedMethods[] = 'OPTIONS';
            $allowedMethods[] = 'HEAD';
            header('Allow: ' . implode(', ', $allowedMethods));

            // FIXME: make CORS configurable by the application, maybe even
            // control per request or page?
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($request->method, $allowedMethods)) {
            // FIXME: I don't like this, but we have to do that even for
            // normal requests. Let's at least limit to 'GET'. Other
            // methods shouldn't lead to requests to foreign sites.
            if ($request->method === 'GET') {
                header('Access-Control-Allow-Origin: *');
            }

            $this->processAllowedMethodRequest($userId, $controller, $request);
        } else {
            $this->sendHttpResponse(405);
        }
    }

    /**
     * Main routine called for pretty much every request. This is where the
     * adapter, the controller and the presentation layer meet to say good
     * night.
     *
     * @return void
     */
    public function main()
    {
        static::sanitizePhp();
        static::checkConfiguration();

        $request = new HttpRequest();
        try {
            $request->populateFrom($_SERVER, $_POST, $_FILES, $_GET);
            $this->processHttpRequest($request);
        } catch (ResourceNotModified $e) {
            static::sendCacheControlHeaders($e->getTimeToLive());
            $this->sendHttpResponse(304);
        } catch (Redirection $e) {
            header('Location: ' . $e->getTargetUrl());
            $this->sendHttpResponse(
                $e->getStatusCode(),
                $e->getTargetUrl()
            );
        } catch (ConfigurationError $e) {
            $this->sendHttpResponse(500, 'server misconfiguration');
            error_log("FATAL: " . $e->getMessage());
        } catch (RouterException $e) {
            $this->sendHttpResponse($e->getStatusCode(), $e->getMessage());
        } catch (Throwable $e) {
            $this->sendHttpResponse(
                500,
                'more information in the error log of the web server'
            );

            ExceptionLogger::logException($e);
        }
    }

    /**
     * Sends the relevant HTTP headers for cache control.
     *
     * @param int $ttl in seconds
     * @return void
     */
    public static function sendCacheControlHeaders(int $ttl)
    {
        $ts = gmdate("D, d M Y H:i:s", time() + $ttl) . " GMT";
        header("Pragma: cache");
        header("Expires: $ts");
        header("Cache-Control: public, max-age=$ttl");
    }

    /**
     * Refine a single menu entry: adds links to menu entries; implemented
     * here because the BaseRequestHandler doesn't have a resolver and links
     * are considered HTTP / HTML specific.
     *
     * @param Element $element element for which to refine the menu entry
     * @param string  $lang    chosen language
     * @param array   $entry   the menu entry to populate
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('lang')
     */
    public function refineMenuEntry(
        Element $element,
        $lang,
        array &$entry
    ) {
        // FIXME: for the datahouse website, this should add css classes:
        // "active bold" for an active link, and color codes (like "nature")
        // for each branch of the top level tree.
        //
        // Originally, that's the mapping:
        //    Dienstleistungen: nature
        //    Produkte:         cold
        //    Technologie:      evencolder
        //    Kunden:           warm

        switch ($element->getType()) {
            case 'page':
            case 'search':
                // Pages must have a slug from which we can form URLs.
                try {
                    $urlp = $this->resolver->getLinkForElement($element);
                    $config = $this->collector->getConfiguration();
                    $entry['link'] = $config->rootUrl . $urlp->getUrl();
                } catch (NoUrlPointer $e) {
                    error_log($e->getMessage());
                }
                break;

            case 'snippet':
                // Provide a link for editing snippets.
                $config = $this->collector->getConfiguration();
                $entry['link'] = $config->rootUrl
                    . '/snippet/' . $element->getId();
                break;

            case 'collection':
                // No link for collections.
                break;

            default:
                error_log(
                    "element type '" . $element->getType() .
                    "' not handled in refineMenuEntry"
                );
        }
    }
}
