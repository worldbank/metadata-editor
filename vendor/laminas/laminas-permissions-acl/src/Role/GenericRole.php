<?php

namespace Laminas\Permissions\Acl\Role;

class GenericRole implements RoleInterface
{
    /**
     * Unique id of Role
     *
     * @var string
     */
    protected $roleId;

    /**
     * Sets the Role identifier
     *
     * @param string $roleId
     */
    public function __construct($roleId)
    {
        $this->roleId = (string) $roleId;
    }

    /**
     * Defined by RoleInterface; returns the Role identifier
     *
     * @return string
     */
    public function getRoleId()
    {
        return $this->roleId;
    }

    /**
     * Defined by RoleInterface; returns the Role identifier
     * Proxies to getRoleId()
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getRoleId();
    }
}
