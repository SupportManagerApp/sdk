<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Security;

use SupportManager\Sdk\Data\Security\TrustedDevice;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Concerns\Untenanted;
use SupportManager\Sdk\Resources\Resource;

/**
 * The devices the authenticated user has trusted, which sign in without device
 * verification until they expire: the web profile's Trusted Devices panel.
 * User-scoped, so no `X-Team-ID` goes out.
 *
 * "This device" is the one the request's access token was issued to: the
 * platform links each token pair to the trusted device it signed in from.
 * {@see TrustedDevice::$isCurrentDevice} flags it, and
 * {@see self::destroyOthers()} keeps it. Hide the remove button for it, as the
 * web does. Tokens issued before the platform linked tokens to devices mark no
 * device as current until the next sign-in.
 *
 * Revoking a device signs it out: the platform revokes the API tokens issued to
 * it, and it must verify again at its next sign-in. API clients are recognised
 * by the `X-Device-Token` header alone, so pass the stored
 * `session()->deviceToken()` on every login, or each one asks for verification.
 *
 * ```php
 * SupportManager::trustedDevices()->all();
 * SupportManager::trustedDevices()->destroy(12);
 * SupportManager::trustedDevices()->destroyOthers();   // the number revoked
 * ```
 *
 * @extends Resource<TrustedDevice>
 */
final class TrustedDevicesResource extends Resource
{
    use Untenanted;

    /**
     * Every trusted device, most recently used first (`GET user/trusted-devices`).
     *
     * @return list<TrustedDevice>
     */
    public function all(): array
    {
        return $this->collection($this->get($this->path()));
    }

    /**
     * Revoke one trusted device (`DELETE user/trusted-devices/{device}`). The
     * platform answers 404 for a device that is not the user's.
     */
    public function destroy(string|int $id): void
    {
        $this->throwForErrorResponse($this->delete("{$this->path()}/{$id}"));
    }

    /**
     * Revoke every trusted device except this one (`DELETE user/trusted-devices`),
     * returning how many were revoked.
     */
    public function destroyOthers(): int
    {
        $revoked = $this->raw($this->delete($this->path()))['revoked_count'] ?? 0;

        return is_int($revoked) ? $revoked : 0;
    }

    protected function path(): string
    {
        return 'user/trusted-devices';
    }

    protected function hydrate(ResourceObject $object): TrustedDevice
    {
        return TrustedDevice::fromArray($object->flat());
    }
}
