<?php

namespace Datahouse\Elements\Control\TextSearch;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Factory;
use Datahouse\Elements\ReFactory;
use Datahouse\Elements\Tools\BgJob;

/**
 * Definition of the background worker job for full text search reindexing.
 *
 * @package Datahouse\Elements\Control\TextSearch
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class FullReindexJob implements BgJob
{
    /* @var Configuration $config */
    public $config;

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        $result = new stdClass();
        $result->{'config'} = $this->config;
        return $result;
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        $this->config = new Configuration('', [], '', '', []);
        $this->config->deserialize($data->{'config'});
    }

    /**
     * @return void
     */
    public function execute()
    {
        $factory = new Factory($this->config);
        $refactory = new ReFactory($factory);

        $collector = $factory->getContentCollector();
        $esInterface = new ElasticsearchInterface($refactory, $collector);

        $collector->triggerFTSFullReindex($esInterface);
    }
}
