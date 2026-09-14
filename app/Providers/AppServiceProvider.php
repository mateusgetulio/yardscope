<?php

namespace App\Providers;

use App\Extraction\AgentExtractor;
use App\Extraction\ClaudeCodeExtractor;
use App\Extraction\FixtureExtractor;
use App\Extraction\RecordsObservations;
use App\Extraction\YardObservationAgent;
use App\Scoping\Data\RateCard;
use App\Scoping\VisionExtractor;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RateCard::class, fn (): RateCard => RateCard::fromArray(config()->array('yardscope.rates')));

        $this->app->bind(AgentExtractor::class, fn (): AgentExtractor => new AgentExtractor(
            new YardObservationAgent,
            config()->string('yardscope.extraction.provider'),
            config()->string('yardscope.extraction.model'),
        ));

        $this->app->bind(ClaudeCodeExtractor::class, fn (): ClaudeCodeExtractor => new ClaudeCodeExtractor(
            new YardObservationAgent,
            config()->string('yardscope.extraction.claude_code.binary'),
            config()->string('yardscope.extraction.claude_code.model'),
            config()->integer('yardscope.extraction.claude_code.timeout'),
        ));

        $this->app->bind(RecordsObservations::class, fn (): RecordsObservations => match (config()->string('yardscope.extraction.driver')) {
            'api' => $this->app->make(AgentExtractor::class),
            'claude-code' => $this->app->make(ClaudeCodeExtractor::class),
            default => throw new RuntimeException('Recording needs a live extractor: set YARDSCOPE_EXTRACTOR to api or claude-code.'),
        });

        $this->app->bind(VisionExtractor::class, fn (): VisionExtractor => match (config()->string('yardscope.extraction.driver')) {
            'api' => $this->app->make(AgentExtractor::class),
            'claude-code' => $this->app->make(ClaudeCodeExtractor::class),
            'fixtures' => new FixtureExtractor(self::fixturesDirectory()),
            default => throw new RuntimeException('Unknown extraction driver ['.config()->string('yardscope.extraction.driver').'].'),
        });
    }

    /**
     * The fixtures directory from config, relative to the project unless given as an absolute path.
     */
    public static function fixturesDirectory(): string
    {
        $configured = config()->string('yardscope.extraction.fixtures');

        return str_starts_with($configured, '/') ? $configured : base_path($configured);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
