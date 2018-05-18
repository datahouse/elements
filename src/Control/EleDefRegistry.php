<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Presentation\BasePageDefinition;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * A simple registry class allowing lookups of IElementDefinitions by name.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class EleDefRegistry
{
    /* @var array $definitions registered by the application */
    protected $definitions;
    /* @var string $defaultPageDefId to use for new elements */
    protected $defaultPageDefId;

    /**
     * ElementDefinitionRegistry constructor.
     *
     * @param string $defaultId default template
     * @param array  $defs      map of id to element definition's class name
     */
    public function __construct(string $defaultId, array $defs)
    {
        $this->defaultPageDefId = $defaultId;
        $this->definitions = $defs;
    }

    /**
     * @param string $id of the IElementDefinition
     * @return IElementDefinition instance
     */
    public function getEleDefById(string $id) : IElementDefinition
    {
        if (array_key_exists($id, $this->definitions)) {
            return new $this->definitions[$id];
        } else {
            throw new \RuntimeException(
                "Element definition '" . $id . "' not found."
            );
        }
    }

    /**
     * getDefaultEleDef
     *
     * @return string
     */
    public function getDefaultEleDef()
    {
        return $this->defaultPageDefId;
    }

    /**
     * Get all selectable page definitions.
     *
     * @return array map of definition ids to class names
     */
    public function enumPageDefIds() : array
    {
        $result = [];
        foreach ($this->definitions as $id => $className) {
            $instance = new $className;
            if ($instance instanceof BasePageDefinition) {
                $result[$id] = $instance->getDisplayName();
            }
        }
        return $result;
    }
}
