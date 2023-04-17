<?php

namespace Shortcut;

use Shortcut\Api\IterationApi;
use Shortcut\Api\MembersApi;
use Shortcut\Api\StoriesApi;
use DateTime;

class Shortcut
{
    public string $api_key;

    public function __construct()
    {
        $this->api_key = getenv('API_KEY');
    }

    /**
     * Find the current iteration.
     */
    public function getCurrentIteration(): object|null
    {
        $iteration_api = new IterationApi($this->api_key);
        $current_date = new DateTime();

        $iterations = $iteration_api->listIterations();
        if (!$iterations) {
            return null;
        }

        foreach ($iterations as $iteration) {
            $end = new DateTime($iteration->end_date);
            $start = new DateTime($iteration->start_date);

            if ($current_date <= $end && $current_date >= $start) {
                return $iteration;
            }
        }

        return null;
    }

    /**
     * Find the current iteration's ID.
     */
    public function getCurrentIterationId(): int|null
    {
        return $this->getCurrentIteration()->id ?? null;
    }

    /**
     * Find Find an iteration by its ID.
     */
    public function getIteration(int $id)
    {
        $iteration_api = new IterationApi($this->api_key);
        return $iteration_api->getIteration($id);
    }

    /**
     * Generate a developer 'scoreboard'.
     */
    public function getDeveloperScoreboardData(?int $iteration_id = null): array|bool
    {
        $iteration_api = $iteration_api = new IterationApi($this->api_key);
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();

        if (!$stories = $iteration_api->getStoriesFromIteration($iteration_id)) {
            return false;
        }

        $members_api = new MembersApi($this->api_key);
        if (empty($devs = $this->getFilteredDevIdsAndNames($members_api->getMembers()))) {
            return false;
        }

        $dev_points = [];
        foreach ($devs as $key => $value) {
            $dev_points[$value] = $this->extractDevPointsFromStories($stories, $key);
        }

        arsort($dev_points);
        return $dev_points;
    }

    /**
     * Generate a developer points reviewed 'scoreboard'.
     */
    public function getDeveloperReviewScoreboardData(?int $iteration_id = null): array|bool
    {
        $iteration_api = $iteration_api = new IterationApi($this->api_key);
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();

        if (!$stories = $iteration_api->getStoriesFromIteration($iteration_id)) {
            return false;
        }

        $members_api = new MembersApi($this->api_key);
        if (empty($devs = $this->getFilteredDevLabelsAndNames($members_api->getMembers()))) {
            return false;
        }

        $dev_points = [];
        foreach ($devs as $key => $value) {
            $dev_points[$value] = $this->extractDevReviewPointsFromStories($stories, $key);
        }

        arsort($dev_points);
        return $dev_points;
    }

    /**
     * Get a developer scoreboard as a string.
     */
    public function developerScoreBoardAsString(?int $iteration_id = null): string|bool
    {
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();
        $dev_points = $this->getDeveloperScoreboardData($iteration_id);
        if (empty($dev_points)) {
            return false;
        }

        $title = '### Total Number of points in iteration per team member';
        return $this->makeScoreboardString($title, $dev_points);
    }

    /**
     * Get a developer review scoreboard as a string.
     */
    public function developerReviewScoreBoardAsString(?int $iteration_id = null): string|bool
    {
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();
        $dev_points = $this->getDeveloperReviewScoreboardData($iteration_id);
        if (empty($dev_points)) {
            return false;
        }

        $title = '### Total Number of points reviewed in iteration per team member';
        return $this->makeScoreboardString($title, $dev_points);
    }

    /**
     * Remove members if they are not part of the list of devs stored in $_ENV.
     */
    protected function filterActiveDevs(array $members): array
    {
        $active_members = [];
        foreach($members as $member) {
            if (
                !$member->disabled
                && $member->role !== 'observer'
                && in_array($member->profile->email_address, array_map('trim', explode(',', getenv('DEV_EMAILS'))))
            ) {
                $active_members[] = $member;
            }
        }

        return $active_members;
    }

    /**
     * Get a list of filtered devs in a associative array format of: [id => name]
     */
    protected function getFilteredDevIdsAndNames(array $members): array
    {
        $ids_and_names = [];
        $devs = $this->filterActiveDevs($members);
        foreach ($devs as $dev) {
            $ids_and_names[$dev->id] = $dev->profile->name;
        }

        return $ids_and_names;
    }

    /**
     * Get a list of filtered devs in a associative array format of: [label => name]
     */
    protected function getFilteredDevLabelsAndNames(array $members): array
    {
        $labels_and_names = [];
        $devs = $this->filterActiveDevs($members);
        $labels = array_map('trim', explode(',', getenv('DEV_LABELS')));

        foreach ($devs as $dev) {
            foreach ($labels as $label) {
                $parts = explode(' ', $label);
                if (str_contains($dev->profile->email_address, strtolower($parts[0]))) {
                    $labels_and_names[$label] = $dev->profile->name;
                }
            }
        }

        return $labels_and_names;
    }

    /**
     * Get the total points per dev.
     */
    protected function extractDevPointsFromStories(iterable $stories, string $dev_id): int
    {
        $points = 0;
        foreach ($stories as $story) {

            if (in_array($dev_id, $story->owner_ids) && !is_null($story->completed_at)) {
                $points += ($story->estimate / count($story->owner_ids));
            }
        }

        return $points;
    }

    /**
     * Get the total review points per dev.
     */
    protected function extractDevReviewPointsFromStories(iterable $stories, string $label): int
    {
        $points = 0;
        foreach ($stories as $story) {
            $labels = array_column($story->labels, 'name');
            if (in_array($label, $labels) && !in_array('REQUEST CHANGES', $labels) && !is_null($story->completed_at)) {
                $points += $story->estimate;
            }
        }

        return $points;
    }

    /**
     * Get the average TIR (days) and total TIR (mins) in a human readable string.
     */
    public function timeInReview(?int $iteration_id = null): array
    {
        $iteration_api = new IterationApi($this->api_key);
        $stories_api = new StoriesApi($this->api_key);
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();

        if (!$stories = $iteration_api->getStoriesFromIteration($iteration_id)) {
            return false;
        }

        $total_review_time = 0;
        $completed_stories = 0;
        foreach ($stories as $story) {
            if ($story->completed) {
                $story_id = $this->getStoryIdFromUrl($story->app_url);
                $story_history = $stories_api->getStoryHistory($story_id);
                $actions = $this->extractWorkFlowActions($story_history);
                $start = $this->getFirstReviewDateTime($actions);
                $end = $this->getLastDeployDateTime($actions);

                $total_review_time += $this->calculateTimeInReview($start, $end);
                $completed_stories ++;
            }
        }

        $average_mins = $total_review_time / $completed_stories;
        $average_days = round($average_mins / 1440, 2);

        return [
            $total_review_time,
            $average_days,
        ];
    }

    /**
     * Get the total number of stories.
     */
    public function countStories(?int $iteration_id = null): int|bool
    {
        $iteration_api = $iteration_api = new IterationApi($this->api_key);
        $iteration_id = $iteration_id ?? $this->getCurrentIterationId();

        if (!$stories = $iteration_api->getStoriesFromIteration($iteration_id)) {
            return false;
        }

        return count($stories);
    }

    /**
     * Get an ID from a shortcut app url.
     */
    protected function getStoryIdFromUrl(string $url): int
    {
        $parts = explode('/', $url);
        return (int) end($parts);
    }

    /**
     * Gets workflow actions out of a story history object.
     */
    protected function extractWorkFlowActions(iterable $story_history): array
    {
        $workflow_actions = [];
        foreach ($story_history as $event) {
            if (property_exists($event, 'references')) {
                foreach ($event->references as $reference) {
                    if ($reference->entity_type == "workflow-state") {
                        $reference->changed_at = $event->changed_at;
                        $workflow_actions[] = $reference;
                    }
                }
            }
        }

        return $workflow_actions;
    }

    /**
     * Finds the first time a story was put into 'Ready for Review'.
     */
    private function getFirstReviewDateTime(array $actions): string
    {
        $review_states = [];
        foreach ($actions as $action) {
            if ($action->name === 'Ready for Review') {
                $review_states[] = $action;
            }
        }

        if (empty($review_states)) {
            return '';
        }

        usort($review_states, [$this, 'compareDates']);
        return $review_states[0]->changed_at;
    }

    /**
     * Finds the last time a story was put into 'Ready for Deploy'.
     */
    private function getLastDeployDateTime(array $actions): string
    {
        $deploy_states = [];
        foreach ($actions as $action) {
            if ($action->name === 'Ready for Deploy') {
                $deploy_states[] = $action;
            }
        }

        if (empty($deploy_states)) {
            return '';
        }

        usort($deploy_states, [$this, 'compareDates']);
        return end($deploy_states)->changed_at;
    }

    /**
     * Calculate a time difference in minutes.
     *
     * @param string $start Datetime string.
     * @param string $end Datetime string.
     *
     * @return integer Interval in minutes.
     */
    private function calculateTimeInReview(string $start, string $end): int|bool
    {
        if (empty($start) || empty($end)) {
            return false;
        }

        $start_object = new DateTime($start);
        $end_object = new DateTime($end);
        $diff = $start_object->diff($end_object);

        $minutes = 0;
        $minutes += $diff->d * 1440;
        $minutes += $diff->h * 60;
        $minutes += $diff->i;

        // Calculate and subtract weekend hours
        $weekend_days = 0;
        for($i = $start_object; $i <= $end_object; $i->modify('+1 day')){
            if (in_array($i->format('N'), [6, 7])) {
                $weekend_days ++;
            }
        }

        $minutes -= ($weekend_days * 1440);
        return $minutes;
    }

    /**
     * Callback function for sorting objects in 'changed_at' in order ASC.
     */
    private function compareDates(object $a, object $b): int
    {
        $first_date = strtotime($a->changed_at);
        $second_date = strtotime($b->changed_at);

        if ($first_date == $second_date) {
            return 0;
        }

        return $first_date < $second_date ? -1 : 1;
    }

    /**
     * Upload a report string to Shortcut
     */
    public function uploadReport(int $iteration_id, string $report)
    {
        $iteration_api = new IterationApi($this->api_key);
        return $iteration_api->updateIteration($iteration_id, ['description' => $report]);
    }

    /**
     * Take some key value data and turn it into a scoreboard string.
     */
    protected function makeScoreboardString(string $title, array $data): string
    {
        $scoreboard = "\n$title\n";
        $position = 1;
        foreach($data as $key => $value) {
            $string = $position . '. ' . $key . ': ' . $value . " points\n";
            $scoreboard .= $string;
            $position ++;
        }

        return $scoreboard;
    }

    public function generateReport($iteration_id)
    {
        $iteration = $this->getIteration($iteration_id);
        $description = $iteration->description;

        [$total_review_time, $average_days] = $this->timeInReview($iteration_id);
        $number_of_stories = $this->countStories($iteration_id);

        $title = '# Shortcut Stats Report';
        $report = "\n\n" . $title;
        $report .= "\nTotal time in review (mins): $total_review_time\n";
        $report .= "Total number of stories: $number_of_stories\n";
        $report .= "Average time in review per story: $average_days days\n";
        $report .= $iteration->stats->num_points_done . ' points completed in ' . $iteration->name . "\n";
        $report .= $this->developerScoreBoardAsString($iteration_id);
        $report .= $this->developerReviewScoreBoardAsString($iteration_id);

        $parts = explode($title, $description);
        $report = trim($parts[0]) . $report;

        return $report;
    }
}
