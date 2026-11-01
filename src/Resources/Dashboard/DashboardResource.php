<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Dashboard;

use SupportManager\Sdk\Data\Dashboard\Capabilities;
use SupportManager\Sdk\Data\Dashboard\DashboardLayout;
use SupportManager\Sdk\Data\Dashboard\WeatherForecast;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;
use SupportManager\Sdk\Session\AuthSession;

/**
 * The dashboard layout, the current user's capabilities in the team, and the
 * dashboard module's own weather widget.
 *
 * `layout()` says which widgets, quick links and alerts the dashboard shows —
 * not the widgets' contents, which come from each module's own resource.
 * `weather()` is the content of the `dashboard.weather` widget.
 * `capabilities()` says which parts of the app to show: role, subscription,
 * plan features, optional modules and navigation. Unlike the rest of the API,
 * `capabilities()` still answers for a team without a subscription (with
 * `subscribed: false`), so a client can explain why everything else 403s.
 *
 * All three endpoints return plain `{data, meta}` payloads (not JSON:API resource
 * objects), so they are read with {@see Resource::raw()} rather than through
 * a fluent query; the resource has no `query()`, `find()` or `all()`.
 *
 * ```php
 * SupportManager::dashboard()->layout()->widgets;
 * SupportManager::dashboard()->weather()?->current->temperature;
 * SupportManager::dashboard()->capabilities()->usesModule(OptionalModule::Accidents);
 * ```
 *
 * @extends Resource<DashboardLayout>
 */
final class DashboardResource extends Resource
{
    private const CAPABILITIES = 'dashboard.capabilities';

    /**
     * The widgets, quick links and alerts the dashboard shows the user.
     */
    public function layout(): DashboardLayout
    {
        return DashboardLayout::fromArray($this->raw($this->get($this->path())));
    }

    /**
     * The `dashboard.weather` widget's forecast: the current weather and the
     * coming days, or null while the platform cannot get a forecast from
     * Open-Meteo (it answers `{data: null}`; hide the widget then, as the web
     * app does). The platform caches the forecast for 30 minutes.
     *
     * No optional module or plan feature gates it: every role that sees the
     * widget may read it. Former PAs, who do not see it, get a 403, and so does
     * a team without an active subscription; both throw a
     * `SupportManagerApiException`.
     */
    public function weather(): ?WeatherForecast
    {
        return $this->data($this->get("{$this->path()}/weather"), WeatherForecast::class);
    }

    /**
     * What the user can do in the current team.
     *
     * Fetched once and then remembered for the session, since hosts check it
     * wherever they gate a module and it rarely changes: see
     * {@see AuthSession::remember()} for when it is dropped. Pass `fresh: true`
     * to fetch it again (and remember the new answer), e.g. on pull-to-refresh.
     */
    public function capabilities(bool $fresh = false): Capabilities
    {
        $session = $this->api->session();

        if ($fresh) {
            $session->forgetRemembered(self::CAPABILITIES);
        }

        return $session->remember(
            self::CAPABILITIES,
            fn (): Capabilities => Capabilities::fromArray($this->raw($this->get('capabilities'))),
        );
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
