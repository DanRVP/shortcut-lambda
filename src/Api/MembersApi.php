<?php

namespace Shortcut\Api;

/**
 * Handles requests for iteration endpoints
 */
class MembersApi extends Api
{
    /**
     * Get a list of all organisation members.
     */
    public function getMembers()
    {
        return $this->makeRequest(function () {
            return $this->getClient()->get("members");
        });
    }
}
