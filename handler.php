<?php declare(strict_types=1);


require __DIR__ . '/vendor/autoload.php';

use Shortcut\Shortcut;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;

class Handler extends EventBridgeHandler
{
    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $shortcut = new Shortcut();

        $iteration_id = $shortcut->getCurrentIterationId();
        $iteration = $shortcut->getIteration($iteration_id);
        $description = $iteration->description;

        [$total_review_time, $average_days] = $shortcut->timeInReview($iteration_id);
        $number_of_stories = $shortcut->countStories($iteration_id);

        $title = '# Shortcut Stats Report';
        $report = "\n\n" . $title;
        $report .= "\nTotal time in review (mins): $total_review_time\n";
        $report .= "Total number of stories: $number_of_stories\n";
        $report .= "Average time in review per story: $average_days days\n";
        $report .= $iteration->stats->num_points_done . ' points completed in ' . $iteration->name . "\n";
        $report .= $shortcut->developerScoreBoardAsString($iteration_id);
        $report .= $shortcut->developerReviewScoreBoardAsString($iteration_id);

        $parts = explode($title, $description);
        $report = trim($parts[0]) . $report;

        $shortcut->uploadReport($iteration_id, $report);
    }
}

return new Handler();
