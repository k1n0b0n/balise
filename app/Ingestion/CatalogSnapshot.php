<?php

declare(strict_types=1);

namespace App\Ingestion;

interface CatalogSnapshot
{
    /**
     * Version of the source, sent back on the next fetch to skip unchanged downloads.
     */
    public function etag(): ?string;

    /**
     * @return iterable<CatalogLine>
     */
    public function lines(): iterable;

    /**
     * @return iterable<CatalogStop>
     */
    public function stops(): iterable;

    /**
     * Empty when the source draws no shapes.
     *
     * @return iterable<CatalogShape>
     */
    public function shapes(): iterable;
}
