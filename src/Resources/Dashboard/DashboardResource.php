<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Dashboard;

use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Dashboard\DashboardLayout;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The dashboard layout and the current user's capabilities in the team.
 *
 * `layout()` says which widgets, quick links and alerts the dashboard shows —
 * not the widgets' contents, which come from each module's own resource.
 * `capabilities()` says which parts of the app to show: role, subscription,
 * plan features, optional modules and navigation. Unlike the rest of the API,
 * `capabilities()` still answers for a team without a subscription (with
 * `subscribed: false`), so a client can explain why everything else 403s.
 *
 * Both endpoints return plain `{data, meta}` payloads (not JSON:API resource
 * objects), so they are read with {@see Resource::raw()} rather than through
 * the fluent {@see query()} builder.
 *
 * ```php
 * SupportManager::dashboard()->layout()->widgets;
 * SupportManager::dashboard()->capabilities()->usesModule(OptionalModule::Accidents);
 * ```
 *
 * @extends \SupportManager\Sdk\Resources\Resource<DashboardLayout>
 */
final class DashboardResource extends Resource
{
    /**
     * The widgets, quick links and alerts the dashboard shows the user.
     */
    public function layout(): DashboardLayout
    {
        return DashboardLayout::fromArray($this->raw($this->get($this->path())));
    }

    /**
     * What the user can do in the current team.
     */
    public function capabilities(): Capabilities
    {
        return Capabilities::fromArray($this->raw($this->get('capabilities')));
    }

    protected function path(): string
    {
        return 'dashboard';
    }

    protected function hydrate(ResourceObject $object): DashboardLayout
    {
        return DashboardLayout::fromArray($object->flat());
    }
}
