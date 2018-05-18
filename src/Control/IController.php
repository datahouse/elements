<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\User;

/**
 * Interface IController
 *
 * All requests, whether admin frontend or public website need to go through
 * such a controller. OTOH it's pretty trivial
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IController
{
    /**
     * Enum the methods supported by this controller. Note that this
     * shouldn't include OPTIONS or HEAD.
     *
     * @return string[]
     */
    public function enumAllowedMethods();

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse;
}
