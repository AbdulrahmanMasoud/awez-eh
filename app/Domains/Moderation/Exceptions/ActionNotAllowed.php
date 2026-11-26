<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Exceptions;

use App\Domains\Moderation\Enums\ContentAction;
use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The content's status does not allow this action now (ContentStatusMachine). The client gets the
 * actions it may take instead.
 */
final class ActionNotAllowed extends DomainException
{
    /**
     * @param  list<ContentAction>  $allowed
     */
    public function __construct(private readonly array $allowed)
    {
        parent::__construct('action_not_allowed', 'errors.action_not_allowed', Response::HTTP_CONFLICT);
    }

    public function errors(): array
    {
        return ['allowed_actions' => array_map(fn (ContentAction $action): string => $action->value, $this->allowed)];
    }
}
