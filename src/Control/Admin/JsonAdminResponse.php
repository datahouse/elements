<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Control\BaseJsonResponse;

/**
 * Represents a JSON response for use with admin ajax requests, which should
 * be handled by a single, common handler on the browser side.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class JsonAdminResponse extends BaseJsonResponse
{
    private $xid;
    private $errorMessages;
    private $infoMessages;
    private $clientInfo;
    private $changes;

    /**
     * @param int         $code   http response code
     * @param string|null $errmsg optional error message
     */
    public function __construct(int $code, string $errmsg = null)
    {
        parent::__construct($code);
        $this->xid = null;
        $this->infoMessages = [];
        $this->errorMessages = isset($errmsg) ? [$errmsg] : [];
        $this->clientInfo = [];
        $this->changes = [];
    }

    /**
     * @param TransactionResult $result to transform
     * @return JsonAdminResponse
     */
    public static function fromTransactionResult(
        TransactionResult $result
    ) : JsonAdminResponse {
        $response = new JsonAdminResponse(200);
        $response->xid = $result->getTransactionId();
        if (!$result->isSuccess()) {
            $response->code = 403;
            $response->errorMessages = $result->getErrorMessages();
        } else {
            assert(count($result->getErrorMessages()) == 0);
            $response->infoMessages = $result->getInfoMessages();
            $response->clientInfo = $result->getClientInfo();
            $response->changes = $result->getChanges();
        }
        return $response;
    }

    /**
     * @return array entire response as a serializable array
     */
    public function asArray() : array
    {
        $arr = [];
        // FIXME: shouldn't really be needed.
        $arr['success'] = $this->isSuccess();
        if (count($this->errorMessages) > 0) {
            $arr['message'] = implode(', ', $this->errorMessages);
        } elseif (count($this->infoMessages) > 0) {
            $arr['message'] = implode(', ', $this->infoMessages);
        }
        if (count($this->clientInfo) > 0) {
            $arr['client_info'] = $this->clientInfo;
        }
        if (count($this->changes) > 0) {
            $arr['changes'] = $this->changes;
        }
        return $arr;
    }

    /**
     * @param string $msg to append
     * @return void
     */
    public function addErrorMessage(string $msg)
    {
        $this->errorMessages[] = $msg;
    }

    /**
     * @param string       $elementId  changed
     * @param string       $actionName performed
     * @param string|array $value      describing the change
     * @return void
     */
    public function appendClientInfo(
        string $elementId,
        string $actionName,
        $value
    ) {
        assert(is_string($value) || is_array($value));
        $this->clientInfo[$elementId][] = [$actionName, $value];
    }
}
