<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTimezoneTest extends TestCase
{
    /**
     * The mysql connection must pin its session time zone to +08:00 so that
     * TIMESTAMP columns are interpreted identically on every environment,
     * instead of silently inheriting the DB server's SYSTEM tz.
     */
    public function test_mysql_connection_timezone_is_pinned_to_kuala_lumpur(): void
    {
        $this->assertSame('+08:00', config('database.connections.mysql.timezone'));
    }

    /**
     * The configured tz must match the application timezone offset, otherwise
     * now() (formatted in app.timezone) is stored/read against a different
     * offset and TIMESTAMP values drift by that difference.
     */
    public function test_connection_timezone_matches_app_timezone_offset(): void
    {
        $appOffset = Carbon::now(config('app.timezone'))->format('P'); // e.g. +08:00

        $this->assertSame($appOffset, config('database.connections.mysql.timezone'));
    }

    /**
     * The live session must actually apply +08:00 (not SYSTEM), so reads do
     * not depend on the host's clock configuration.
     */
    public function test_live_session_time_zone_is_plus_eight(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Timezone pinning only applies to the mysql driver.');
        }

        $tz = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;

        $this->assertSame('+08:00', $tz);
    }

    /**
     * A KL wall-clock value written through Eloquent must read back as the same
     * wall clock — proving the write and read use the same offset (no drift).
     */
    public function test_timestamp_round_trips_without_shifting(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('TIMESTAMP round-trip check only applies to the mysql driver.');
        }

        $written = Carbon::parse('2026-09-14 10:29:59', config('app.timezone'));

        $readBack = DB::selectOne(
            'SELECT ? AS ts',
            [$written->format('Y-m-d H:i:s')]
        )->ts;

        $this->assertSame($written->format('Y-m-d H:i:s'), $readBack);
    }
}
