<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Config;

/**
 * When a network's feed is published (E2.5), so that the poll reads it just after, and browsers ask for the new
 * positions just after the poll has recorded them (E4.1).
 */
final readonly class FeedTiming
{
    /** A feed is served about a second after the time it carries (TBM, measured over two hours, story 024). */
    public const int PUBLICATION_DELAY_SECONDS = 1;

    /** Reading and recording a feed took 0.3 s on the server after it was served (story 024): rounded up. */
    public const int RECORDING_SECONDS = 1;

    /** How long the web server keeps an answer once the expected version is late (E4.2). */
    public const int LATE_ANSWER_SECONDS = 2;

    public function __construct(private Repository $cache) {}

    public function remember(string $network, CarbonImmutable $publishedAt): void
    {
        $this->cache->put($this->key($network), $publishedAt->getTimestamp(), now()->addHour());
    }

    /**
     * The time the last feed carried, null before the first one.
     */
    public function publishedAt(string $network): ?int
    {
        $publishedAt = $this->cache->get($this->key($network));

        // Redis gives numbers back as text.
        return is_numeric($publishedAt) ? (int) $publishedAt : null;
    }

    /**
     * When the positions of the next publication should be recorded, in seconds since the epoch: the next time,
     * after now, that is a whole number of intervals after the last publication, plus the delays above. Null when no
     * feed has been read yet.
     */
    public function nextVersionAt(string $network, CarbonImmutable $now): ?float
    {
        $publishedAt = $this->publishedAt($network);

        if ($publishedAt === null) {
            return null;
        }

        $every = Config::integer("networks.{$network}.vehicles.poll_every_seconds");
        $recorded = $publishedAt + self::PUBLICATION_DELAY_SECONDS + self::RECORDING_SECONDS;
        $nowSeconds = $now->getTimestamp() + $now->micro / 1e6;
        $intervals = $nowSeconds < $recorded ? 0 : (int) floor(($nowSeconds - $recorded) / $every) + 1;

        return (float) ($recorded + $intervals * $every);
    }

    /**
     * Until when the web server may keep an answer of the positions endpoint (E4.2), in seconds since the epoch: the
     * next version's time, or a couple of seconds once the expected version is late, so that a version recorded by
     * the poll's retry reaches pages within the live store's retry. Null when no feed has been read yet.
     */
    public function answerExpiresAt(string $network, CarbonImmutable $now): ?int
    {
        $next = $this->nextVersionAt($network, $now);
        $publishedAt = $this->publishedAt($network);

        if ($next === null || $publishedAt === null) {
            return null;
        }

        // Late: the expected time of the last interval has passed without a newer feed recorded.
        $recorded = $publishedAt + self::PUBLICATION_DELAY_SECONDS + self::RECORDING_SECONDS;
        $late = $next - $recorded > Config::integer("networks.{$network}.vehicles.poll_every_seconds");

        return $late ? min((int) $next, $now->getTimestamp() + self::LATE_ANSWER_SECONDS) : (int) $next;
    }

    private function key(string $network): string
    {
        return "vehicle-feed:{$network}:published-at";
    }
}
