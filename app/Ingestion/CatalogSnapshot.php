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
     * Copies the catalogue as it was downloaded, for the recordings (E5.1).
     */
    public function copyTo(string $path): void;

    /**
     * @return iterable<CatalogLine>
     */
    public function lines(): iterable;

    /**
     * @return iterable<CatalogStop>
     */
    public function stops(): iterable;

    /**
     * Groups of platforms under one name, and the platforms that belong to none (E7.1).
     *
     * @return iterable<CatalogStation>
     */
    public function stations(): iterable;

    /**
     * Empty when the source draws no shapes.
     *
     * @return iterable<CatalogShape>
     */
    public function shapes(): iterable;
}
