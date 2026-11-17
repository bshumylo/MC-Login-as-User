<?php

declare(strict_types=1);

namespace Espo\Modules\LoginAsUser\Classes\Authentication\Hook\Hooks;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\Hook\OnResult;
use Espo\Core\Authentication\Result;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TargetPolicy;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCodec;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCookie;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Replaces the authenticated administrator with the user named in a valid ticket cookie.
 *
 * The ticket is signed by the server, bound to the administrator and to the auth token of the session,
 * and expires. Any invalid ticket is ignored: the request is then served as the administrator.
 */
class LoginAsUser implements OnResult
{
    public const ACTOR_ATTRIBUTE = 'loginAsUserActorId';

    public function __construct(
        private User $user,
        private EntityManager $entityManager,
        private TicketCodec $codec,
        private TargetPolicy $policy,
        private Log $log,
    ) {}

    public function process(Result $result, AuthenticationData $data, Request $request): void
    {
        $value = $request->getCookieParam(TicketCookie::NAME);
        $token = $data->getPassword();
        $admin = $result->getUser();

        if ($value === null || $value === '' || $token === null || $token === '' || $admin === null) {
            return;
        }

        try {
            $ticket = $this->codec->decode($value);
        } catch (Throwable $e) {
            $this->log->error('Login as User: cannot verify the ticket: ' . $e->getMessage());

            return;
        }

        if ($ticket === null) {
            $this->log->warning('Login as User: a ticket with an invalid signature was ignored.', [
                'userId' => $admin->getId(),
            ]);

            return;
        }

        if (
            $ticket->isExpired(time()) ||
            $ticket->actorId !== $admin->getId() ||
            !hash_equals($ticket->tokenHash, TicketCodec::hashToken($token))
        ) {
            return;
        }

        $target = $this->entityManager->getEntityById(User::ENTITY_TYPE, $ticket->targetId);

        if (!$target instanceof User || !$this->policy->isAllowed($admin, $target)) {
            return;
        }

        // The same preparation the core does for a logged user: team-based ACL needs the teams.
        if (!$target->isPortal()) {
            $target->loadLinkMultipleField('teams');
        }

        // Read everything from the administrator before the swap: in the core the user of the result
        // and the logged user are the same object, so after the swap it already describes the target.
        $adminId = $admin->getId();
        $ipAddress = $admin->get('ipAddress');
        // Keep the ids of the administrator's session (the core sets them on the logged user):
        // Action History records then point to the real login. The token itself is not kept.
        $authTokenId = $this->user->get('authTokenId');
        $authLogRecordId = $this->user->get('authLogRecordId');

        $this->user->reset();
        $this->user->set($target->getValueMap());
        $this->user->set('ipAddress', $ipAddress);
        $this->user->set('authTokenId', $authTokenId);
        $this->user->set('authLogRecordId', $authLogRecordId);
        $this->user->set(self::ACTOR_ATTRIBUTE, $adminId);
    }
}
