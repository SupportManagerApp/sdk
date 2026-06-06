<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing;

/**
 * Implemented by a {@see Factory} whose resource has endpoints the generic REST
 * fake can't infer — non-RESTful actions, flat-singleton reads/writes, or index
 * endpoints with bespoke filtering.
 *
 * The routes a factory returns are registered alongside its seeded data and
 * tried before the generic CRUD fallback, so a resource's quirks live in one
 * place next to the data that feeds them.
 */
interface ProvidesFakeRoutes
{
    /**
     * The custom routes this resource answers.
     *
     * @return list<FakeRoute>
     */
    public function fakeRoutes(): array;
}
