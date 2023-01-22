<?php

namespace Shortcut\Api;

/**
 * Handles requests for iteration endpoints
 */
class IterationApi extends Api
{
    /**
     * Get all stories from a specified integration.
     *
     * @param integer $id Iteration ID.
     */
    public function getStoriesFromIteration($id)
    {
        return $this->makeRequest(function () use ($id) {
            return $this->getClient()->get("iterations/$id/stories");
        });
    }

    /**
     * Get an iteration by its ID.
     *
     * @param int $id
     */
    public function getIteration($id)
    {
        return $this->makeRequest(function () use ($id) {
            return $this->getClient()->get("iterations/$id");
        });
    }

    /**
     * Update an iteration.
     *
     * @param int $id
     * @param array $update fields
     */
    public function updateIteration(int $id, array $update_fields)
    {
        return $this->makeRequest(function () use ($id, $update_fields) {
            return $this->getClient()->put("iterations/$id", ['body' => json_encode($update_fields)]);
        });
    }

    /**
     * Get a list of all iterations.
     */
    public function listIterations()
    {
        return $this->makeRequest(function () {
           return $this->getClient()->get("iterations");
        });
    }
}
