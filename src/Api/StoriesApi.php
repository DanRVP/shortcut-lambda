<?php

namespace Shortcut\Api;

/**
 * Handles requests for iteration endpoints
 */
class StoriesApi extends Api
{
    /**
     * Get all story history events from a specified story.
     *
     * @param int $id Story ID.
     */
    public function getStoryHistory(int $id)
    {
        return $this->makeRequest(function () use ($id) {
            return $this->getClient()->get("stories/$id/history");
        });
    }
}
