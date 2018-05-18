<?php

namespace Datahouse\Elements\Control;

use Assetic\Asset\AssetInterface;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\AccessDenied;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Control\Exceptions\ResourceNotModified;
use Datahouse\Elements\Presentation\IRenderer;
use Datahouse\Elements\Presentation\StaticDataRenderer;

/**
 * A controller for assetic based resources (i.e. css and js stuff).
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class AsseticResourceController extends BaseController
{
    use ReadOnlyMixin;

    /* one year - these are covered by hashes, anyways */
    const HTTP_CACHE_TTL = 31536000;

    private $type;
    private $collection;
    private $filename;

    /**
     * constructor for the AsseticResourceController
     *
     * @param BaseRouter         $router     invoking this controller
     * @param BaseRequestHandler $handler    in charge of the request
     * @param string             $type       of the resource to serve
     * @param string             $collection containing the resource
     * @param string             $filename   of the specific resource
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        string $type,
        string $collection,
        string $filename
    ) {
        parent::__construct($router, $handler);
        $this->type = $type;
        $this->collection = $collection;
        $this->filename = $filename;
    }

    /**
     * @param User $user who triggered this request
     * @return IRenderer
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("user"))
     */
    public function processGet(User $user) : IRenderer
    {
        $ah = $this->handler->getAssetHandler();
        $collection = $ah->getAssetCollection(
            $this->type,
            $this->collection
        );
        if (is_null($collection)) {
            throw new ResourceNotFound();
        }

        $asset = null;
        $hash = AssetHandler::getAssetHash($collection);

        // No matter what exact file within the collection the browser
        // requests, if it already has an E-Tag for it that matches, answer
        // with a 304 Not Modified.
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            $_SERVER['HTTP_IF_NONE_MATCH'] == $hash
        ) {
            throw new ResourceNotModified(static::HTTP_CACHE_TTL);
        }

        if ($this->filename === $hash) {
            $asset = $collection;
        } else {
            /** @var AssetInterface $a */
            foreach ($collection->all() as $a) {
                if ($a->getTargetPath() == $this->filename) {
                    $asset = $a;
                    break;
                }
            }
            if (is_null($asset)) {
                throw new ResourceNotFound();
            } elseif ($ah->isCombined()) {
                throw new AccessDenied(
                    'configured to ship combined collections only'
                );
            }
        }

        $template = new StaticDataRenderer();
        $template->setTemplateData([
            'content_type' => 'text/' . $this->type,
            'content' => $asset->dump(),
            'etag' => $hash,
            'ttl' => static::HTTP_CACHE_TTL
        ]);
        return $template;
    }
}
