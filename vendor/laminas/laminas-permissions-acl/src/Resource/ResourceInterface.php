<?php

namespace Laminas\Permissions\Acl\Resource;

interface ResourceInterface
{
    /**
     * Returns the string identifier of the Resource
     *
     * @return string
     */
    public function getResourceId();
}
