<?php
/**
 * UpdateUserGroupSignal class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version 2014.11.1
 */

namespace eZ\Publish\Core\SignalSlot\Signal\UserService;

use eZ\Publish\Core\SignalSlot\Signal;

/**
 * UpdateUserGroupSignal class
 * @package eZ\Publish\Core\SignalSlot\Signal\UserService
 */
class UpdateUserGroupSignal extends Signal
{
    /**
     * UserGroupId
     *
     * @var mixed
     */
    public $userGroupId;
}
