<?php

declare(strict_types=1);

use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentActor;
use App\Domains\Moderation\Enums\ContentDeal;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Support\ContentState;
use App\Domains\Moderation\Support\ContentStatusMachine;

mutates(ContentStatusMachine::class, ContentState::class);

/**
 * The specification, written apart from the machine: for a status, an action, the content's deal
 * and whether it changed since its last review, who may act and where it lands. Every
 * combination not listed is refused to everyone.
 */
function expectedContentTransition(ContentStatus $from, ContentAction $action, ?ContentDeal $deal, bool $changed): ?array
{
    $live = [ContentStatus::Published, ContentStatus::Paused];
    $nonTerminal = [ContentStatus::Draft, ContentStatus::PendingReview, ContentStatus::Published, ContentStatus::Paused, ContentStatus::Rejected];
    $owner = ContentActor::Owner;
    $moderator = ContentActor::Moderator;

    return match (true) {
        $action === ContentAction::Submit && $from === ContentStatus::Draft => [$owner, ContentStatus::PendingReview],
        $action === ContentAction::Submit && $from === ContentStatus::Rejected && $changed => [$owner, ContentStatus::PendingReview],
        $action === ContentAction::Approve && $from === ContentStatus::PendingReview => [$moderator, ContentStatus::Published],
        $action === ContentAction::Reject && $from === ContentStatus::PendingReview => [$moderator, ContentStatus::Rejected],
        $action === ContentAction::TakeDown && $from === ContentStatus::Published => [$moderator, ContentStatus::Rejected],
        $action === ContentAction::Pause && $from === ContentStatus::Published => [$owner, ContentStatus::Paused],
        $action === ContentAction::Resume && $from === ContentStatus::Paused => [$owner, $changed ? ContentStatus::PendingReview : ContentStatus::Published],
        $action === ContentAction::MarkSold && $deal === ContentDeal::Sale && in_array($from, $live, true) => [$owner, ContentStatus::Sold],
        $action === ContentAction::MarkRented && $deal === ContentDeal::Rent && in_array($from, $live, true) => [$owner, ContentStatus::Rented],
        $action === ContentAction::Archive && in_array($from, $nonTerminal, true) => [$owner, ContentStatus::Archived],
        default => null,
    };
}

/**
 * Every status, action, deal (sale, rent, none) and changed flag: 8 × 9 × 3 × 2.
 *
 * @return array<string, array{ContentStatus, ContentAction, ?ContentDeal, bool}>
 */
function everyContentTransition(): array
{
    $cases = [];

    foreach (ContentStatus::cases() as $from) {
        foreach (ContentAction::cases() as $action) {
            foreach ([ContentDeal::Sale, ContentDeal::Rent, null] as $deal) {
                foreach ([false, true] as $changed) {
                    $label = sprintf('%s %s, %s%s', $from->value, $action->value, $deal->value ?? 'no deal', $changed ? ', changed' : '');
                    $cases[$label] = [$from, $action, $deal, $changed];
                }
            }
        }
    }

    return $cases;
}

it('lets exactly the right actor make each move, to the right status', function (ContentStatus $from, ContentAction $action, ?ContentDeal $deal, bool $changed): void {
    $machine = new ContentStatusMachine;
    $state = new ContentState($from, $deal, $changed);
    $expected = expectedContentTransition($from, $action, $deal, $changed);

    foreach (ContentActor::cases() as $actor) {
        $allowed = $expected !== null && $expected[0] === $actor;

        expect($machine->next($state, $action, $actor))->toBe($allowed ? $expected[1] : null)
            ->and($machine->allows($state, $action, $actor))->toBe($allowed);
    }
})->with(everyContentTransition());

it('lists the actions each actor may take, in a stable order', function (): void {
    $machine = new ContentStatusMachine;

    expect($machine->allowedActions(new ContentState(ContentStatus::Published, ContentDeal::Sale, false), ContentActor::Owner))
        ->toBe([ContentAction::Pause, ContentAction::MarkSold, ContentAction::Archive])
        ->and($machine->allowedActions(new ContentState(ContentStatus::Published, ContentDeal::Rent, false), ContentActor::Moderator))
        ->toBe([ContentAction::TakeDown])
        ->and($machine->allowedActions(new ContentState(ContentStatus::PendingReview, null, false), ContentActor::Moderator))
        ->toBe([ContentAction::Approve, ContentAction::Reject])
        ->and($machine->allowedActions(new ContentState(ContentStatus::Rejected, ContentDeal::Rent, false), ContentActor::Owner))
        ->toBe([ContentAction::Archive])
        ->and($machine->allowedActions(new ContentState(ContentStatus::Sold, ContentDeal::Sale, true), ContentActor::Owner))
        ->toBe([]);
});

it('ends content for good when it is sold, rented or archived', function (ContentStatus $status, bool $terminal): void {
    $machine = new ContentStatusMachine;

    expect($machine->isTerminal($status))->toBe($terminal)
        ->and($machine->isEditable($status))->toBe(! $terminal);
})->with([
    'draft' => [ContentStatus::Draft, false],
    'pending review' => [ContentStatus::PendingReview, false],
    'published' => [ContentStatus::Published, false],
    'paused' => [ContentStatus::Paused, false],
    'rejected' => [ContentStatus::Rejected, false],
    'sold' => [ContentStatus::Sold, true],
    'rented' => [ContentStatus::Rented, true],
    'archived' => [ContentStatus::Archived, true],
]);

it('sends published content back to review after a change that needs it, and marks paused or rejected content', function (ContentStatus $status, bool $needsReview, ?ContentStatus $after, bool $marked): void {
    $machine = new ContentStatusMachine;

    expect($machine->statusAfterEdit($status, $needsReview))->toBe($after)
        ->and($machine->marksChangeAfterEdit($status, $needsReview))->toBe($marked);
})->with([
    'draft, any change' => [ContentStatus::Draft, true, ContentStatus::Draft, false],
    'draft, the price' => [ContentStatus::Draft, false, ContentStatus::Draft, false],
    'pending review, any change' => [ContentStatus::PendingReview, true, ContentStatus::PendingReview, false],
    'pending review, the price' => [ContentStatus::PendingReview, false, ContentStatus::PendingReview, false],
    'published, a change that needs review' => [ContentStatus::Published, true, ContentStatus::PendingReview, false],
    'published, the price only' => [ContentStatus::Published, false, ContentStatus::Published, false],
    'paused, a change that needs review' => [ContentStatus::Paused, true, ContentStatus::Paused, true],
    'paused, the price only' => [ContentStatus::Paused, false, ContentStatus::Paused, false],
    // Any edit counts before a rejected listing goes back to review, the price included.
    'rejected, a change that needs review' => [ContentStatus::Rejected, true, ContentStatus::Rejected, true],
    'rejected, the price only' => [ContentStatus::Rejected, false, ContentStatus::Rejected, true],
    'sold' => [ContentStatus::Sold, false, null, false],
    'rented' => [ContentStatus::Rented, true, null, false],
    'archived' => [ContentStatus::Archived, true, null, false],
]);

it('asks for a reason to reject or take down, and for nothing else', function (ContentAction $action): void {
    expect(new ContentStatusMachine()->requiresReason($action))
        ->toBe(in_array($action, [ContentAction::Reject, ContentAction::TakeDown], true));
})->with(ContentAction::cases());

it('names who makes each action', function (ContentAction $action): void {
    $moderator = [ContentAction::Approve, ContentAction::Reject, ContentAction::TakeDown];

    expect(new ContentStatusMachine()->actorOf($action))
        ->toBe(in_array($action, $moderator, true) ? ContentActor::Moderator : ContentActor::Owner);
})->with(ContentAction::cases());

it('keeps the action names the API sends, in snake case', function (): void {
    expect(array_column(ContentAction::cases(), 'value'))
        ->toBe(['submit', 'approve', 'reject', 'take_down', 'pause', 'resume', 'mark_sold', 'mark_rented', 'archive']);
});
