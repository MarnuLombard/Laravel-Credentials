<?php

/*
 * This file is part of Laravel Credentials by Graham Campbell.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at http://bit.ly/UWsjkb.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace GrahamCampbell\Credentials\Presenters\RevisionDisplayers\User;

use GrahamCampbell\Credentials\Presenters\RevisionDisplayers\AbstractRevisionDisplayer;
use GrahamCampbell\Credentials\Presenters\RevisionDisplayers\RevisionDisplayerInterface;

/**
 * This is the abstract displayer class.
 *
 * @author    Graham Campbell <graham@mineuk.com>
 * @copyright 2013-2014 Graham Campbell
 * @license   <https://github.com/GrahamCampbell/Laravel-Credentials/blob/master/LICENSE.md> Apache 2.0
 */
abstract class AbstractDisplayer extends AbstractRevisionDisplayer implements RevisionDisplayerInterface
{
    /**
     * Get the change description.
     *
     * @return string
     */
    public function description()
    {
        if ($this->isCurrentUser()) {
            return $this->current();
        }

        return $this->external();
    }

    /**
     * Get the change description from the context of
     * the change being made to the current user.
     *
     * @return string
     */
    abstract protected function current();

    /**
     * Get the change description from the context of
     * the change not being made to the current user.
     *
     * @return string
     */
    abstract protected function external();

    /**
     * Was the action by the actual user?
     *
     * @return bool
     */
    protected function wasActualUser()
    {
        return ($this->wrappedObject->user_id == $this->wrappedObject->revisionable_id || !$this->wrappedObject->user_id);
    }

    /**
     * Is the current user's account?
     *
     * @return bool
     */
    protected function isCurrentUser()
    {
        return ($this->credentials->check() && $this->credentials->getUser()->id == $this->wrappedObject->revisionable_id);
    }

    /**
     * Get the author details.
     *
     * @return string
     */
    protected function author()
    {
        if ($this->presenter->wasByCurrentUser() || !$this->wrappedObject->user_id) {
            return 'You ';
        }

        if (!$this->wrappedObject->security) {
            return 'This user ';
        }

        return $this->presenter->author().' ';
    }

    /**
     * Get the user details.
     *
     * @return string
     */
    protected function user()
    {
        if ($this->wrappedObject->security) {
            return ' this user\'s ';
        }

        $user = $this->wrappedObject->revisionable()->withTrashed()->first(['first_name', 'last_name']);

        return ' '.$user->first_name.' '.$user->last_name.'\'s ';
    }
}
