<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Changes\ElementAttachChildElement;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorable;
use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseJsonController;
use Datahouse\Elements\Control\Exceptions\NoOpException;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\TextSearch\IncrementalReindexJob;
use Datahouse\Elements\Tools\BgWorkerClient;

/**
 * BaseElementAjaxController - a simplifying base class for all POST requests
 * that act on an element, trigger a storage transaction, and respond with
 * JSON.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
abstract class BaseAdminTransactionController extends BaseJsonController
{
    /**
     * @param IChangeProcess $process to use
     * @return callable function for planning the transaction
     */
    abstract public function getTxnPlanningFunc(
        IChangeProcess $process
    ) : callable;

    /**
     * @param IChangeProcess $process to use
     * @param Transaction    $txn     a valid transaction to apply
     * @return TransactionResult of the application of the transaction
     */
    protected function processValidTransaction(
        IChangeProcess $process,
        Transaction $txn
    ) : TransactionResult {
        // FIXME: rather than only asking the process to generate a
        // transaction, we should also verify the user has permissions to
        // apply these changes. That's a task for the auth handler, not
        // the process definition, though.

        $adapter = $this->handler->getAdapter();
        $touchedElementIds = [];
        $visitorFunc = function (
            IStorable $obj
        ) use (
            $process,
            &$touchedElementIds
        ) {
            if ($obj instanceof Element) {
                $process->markUnreachableElementVersions($obj);
                $touchedElementIds[] = $obj->getId();
            }
        };
        $applicationResult = $adapter->applyTransaction($txn, $visitorFunc);

        // Update the full text search index, if needed.
        $bgwClient = new BgWorkerClient();
        if ($bgwClient->isConfigured()) {
            $esInterface = $this->handler->getElasticsearchInterface();
            $job = new IncrementalReindexJob();
            $job->config = $esInterface->getConfiguration();
            $job->elementIds = $touchedElementIds;
            $bgwClient->enqueueJob($job);
        }

        // Tell the frontent the URLs of the elements that changed.
        foreach (array_unique(
            $applicationResult->getTouchedUrls()
        ) as $elementId) {
            $resolver = $this->handler->getContentCollector()->getUrlResolver();
            $element = $adapter->loadElement($elementId);
            try {
                $urlp = $resolver->getLinkForElement($element);
            } catch (NoUrlPointer $e) {
                continue;
            }
            assert(isset($urlp));
            $applicationResult->appendClientInfo(
                $elementId,
                'set_default_url',
                Constants::getRootUrl() . $urlp->getUrl()
            );
        }

        // Check if updating the url mapping is needed
        // FIXME: all of this should move to the storage adapter!
        //        is moved, but the frontend, esp. the admin tree, still needs
        //        the 'changes' info collected here to provide a link after
        //        adding a new element. Bah!
        $urlMappingNeedsUpdate = false;
        foreach ($txn->getChanges() as $change) {
            if ($change instanceof ElementAttachChildElement) {
                $urlMappingNeedsUpdate = true;
                break;
            }
        }

        if ($urlMappingNeedsUpdate) {
            // not so sure this belongs here
            // FIXME: (mwa) I'm sure it doesn't, so *move* this

            $changes = $applicationResult->getChanges();

            // So far, we support creating only one element at a time.
            if (array_key_exists('element_id', $changes)) {
                $urlPointers = array_filter(
                    $adapter->loadUrlPointersByElement(
                        $changes['element_id']
                    ),
                    function (UrlPointer $urlp) {
                        return !$urlp->isDeprecated();
                    }
                );
                if (count($urlPointers) > 0) {
                    $urlp = reset($urlPointers);
                    $applicationResult->addChange(
                        'link',
                        Constants::getRootUrl() . $urlp->getUrl()
                    );
                }
            }
        }

        return $applicationResult;
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process the request
     * @return TransactionResult
     * @throws NoOpException
     */
    protected function processTransaction(
        HttpRequest $request,
        User $user
    ) : TransactionResult {
        $adapter = $this->handler->getAdapter();
        $process = $this->handler->getChangeProcess();
        $planFn = $this->getTxnPlanningFunc($process);
        /* @var Transaction $txn */
        $txn = $planFn($user, $request);
        $txn->setAuthor($user);

        $validationResult = $adapter->validateTransaction($txn);
        if (!$validationResult->isSuccess()) {
            return $validationResult;
        } else {
            return $this->processValidTransaction($process, $txn);
        }
    }
}
