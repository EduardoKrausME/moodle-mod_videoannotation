<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video progress tracking service.
 *
 * @package   mod_videoannotation
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoannotation;

use cm_info;
use completion_info;
use stdClass;

/**
 * Merges watched intervals and maintains server-authoritative progress.
 */
class progress_manager {
    /**
     * Maximum accepted continuous client segment in seconds.
     */
    private const MAX_SEGMENT = 15.0;

    /**
     * Updates one user's progress record.
     *
     * @param stdClass $activity Activity record.
     * @param cm_info|stdClass $cm Course module.
     * @param int $userid User identifier.
     * @param array $data Validated tracking values.
     * @return stdClass Updated progress record with response metadata.
     */
    public function update(stdClass $activity, $cm, int $userid, array $data): stdClass {
        global $DB;

        $now = time();
        $progress = $DB->get_record('videoannotation_progress', [
            'videoannotationid' => $activity->id,
            'userid' => $userid,
        ]);
        if (!$progress) {
            $progress = (object)[
                'videoannotationid' => $activity->id,
                'userid' => $userid,
                'duration' => 0,
                'lastposition' => 0,
                'uniquewatched' => 0,
                'totalwatchtime' => 0,
                'percent' => 0,
                'watchedsegments' => '[]',
                'sequence' => 0,
                'sessionkey' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }

        $duration = max(0.0, (float)$data['duration']);
        $current = max(0.0, min($duration > 0 ? $duration : PHP_FLOAT_MAX, (float)$data['currentposition']));
        $start = max(0.0, (float)$data['segmentstart']);
        $end = max($start, (float)$data['segmentend']);
        $sessionkey = clean_param((string)$data['sessionkey'], PARAM_ALPHANUMEXT);
        $sequence = (int)$data['sequence'];
        $reason = '';

        if ((string)$progress->sessionkey === $sessionkey && $sequence <= (int)$progress->sequence) {
            $progress->reason = 'duplicate';
            return $progress;
        }

        if ($duration > 0) {
            $progress->duration = max((float)$progress->duration, $duration);
        }

        $maxadvance = self::MAX_SEGMENT * max(1.0, min(4.0, (float)($data['playbackrate'] ?? 1.0)));
        if ($end - $start > $maxadvance) {
            $end = $start + $maxadvance;
        }
        $end = $progress->duration > 0 ? min($end, (float)$progress->duration) : $end;

        $segments = $this->decode_segments((string)$progress->watchedsegments);
        $isforwardseek = $current > (float)$progress->lastposition + 4.0 && !$this->contains($segments, $current);
        if (empty($activity->allowseek) && $isforwardseek && (float)$progress->lastposition > 0) {
            $current = (float)$progress->lastposition;
            $reason = 'seekblocked';
        } else if ($end > $start && $end - $start <= $maxadvance + 0.01) {
            $segments[] = [$start, $end];
            $segments = $this->merge_segments($segments);
            $progress->totalwatchtime = (float)$progress->totalwatchtime + ($end - $start);
        }

        $progress->lastposition = $current;
        $progress->uniquewatched = $this->total_duration($segments);
        $progress->percent = $progress->duration > 0
            ? min(100.0, ($progress->uniquewatched / (float)$progress->duration) * 100.0)
            : 0.0;
        $progress->watchedsegments = json_encode($segments, JSON_NUMERIC_CHECK);
        $progress->sequence = $sequence;
        $progress->sessionkey = $sessionkey;
        $progress->timemodified = $now;

        if (empty($progress->id)) {
            $progress->id = $DB->insert_record('videoannotation_progress', $progress);
        } else {
            $DB->update_record('videoannotation_progress', $progress);
        }

        $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }

        $progress->reason = $reason;
        return $progress;
    }

    /**
     * Returns an empty progress object compatible with view rendering.
     *
     * @return stdClass
     */
    public static function empty_progress(): stdClass {
        return (object)[
            'duration' => 0,
            'lastposition' => 0,
            'uniquewatched' => 0,
            'totalwatchtime' => 0,
            'percent' => 0,
            'watchedsegments' => '[]',
        ];
    }

    /**
     * Determines whether a percentage rule has been satisfied.
     *
     * @param stdClass $activity Activity record.
     * @param stdClass|null $progress Progress record.
     * @return bool
     */
    public function percent_complete(stdClass $activity, ?stdClass $progress): bool {
        if (!$progress) {
            return false;
        }
        return (float)$progress->percent >= (float)$activity->completionpercent;
    }

    /**
     * Converts watched segments into template percentages.
     *
     * @param string $json Stored watched segments JSON.
     * @param float $duration Video duration.
     * @return array
     */
    public function timeline(string $json, float $duration): array {
        if ($duration <= 0) {
            return [];
        }
        $items = [];
        foreach ($this->decode_segments($json) as $segment) {
            $start = max(0.0, min($duration, (float)$segment[0]));
            $end = max($start, min($duration, (float)$segment[1]));
            $items[] = [
                'left' => round(($start / $duration) * 100, 4),
                'width' => round((($end - $start) / $duration) * 100, 4),
                'start' => self::format_time($start),
                'end' => self::format_time($end),
            ];
        }
        return $items;
    }

    /**
     * Formats seconds as HH:MM:SS or MM:SS.
     *
     * @param float $seconds Seconds.
     * @return string
     */
    public static function format_time(float $seconds): string {
        $value = max(0, (int)round($seconds));
        $hours = intdiv($value, 3600);
        $minutes = intdiv($value % 3600, 60);
        $remaining = $value % 60;
        return $hours > 0
            ? sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining)
            : sprintf('%02d:%02d', $minutes, $remaining);
    }

    /**
     * Decodes watched segment JSON safely.
     *
     * @param string $json JSON value.
     * @return array
     */
    private function decode_segments(string $json): array {
        $segments = json_decode($json, true);
        if (!is_array($segments)) {
            return [];
        }
        $clean = [];
        foreach ($segments as $segment) {
            if (is_array($segment) && count($segment) >= 2 && is_numeric($segment[0]) && is_numeric($segment[1])) {
                $start = max(0.0, (float)$segment[0]);
                $end = max($start, (float)$segment[1]);
                $clean[] = [$start, $end];
            }
        }
        return $this->merge_segments($clean);
    }

    /**
     * Merges overlapping or nearly adjacent intervals.
     *
     * @param array $segments Segments.
     * @return array
     */
    private function merge_segments(array $segments): array {
        if (!$segments) {
            return [];
        }
        usort($segments, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($segments as $segment) {
            if (!$merged || $segment[0] > $merged[count($merged) - 1][1] + 0.75) {
                $merged[] = [(float)$segment[0], (float)$segment[1]];
                continue;
            }
            $last = count($merged) - 1;
            $merged[$last][1] = max($merged[$last][1], (float)$segment[1]);
        }
        return $merged;
    }

    /**
     * Calculates unique watched duration.
     *
     * @param array $segments Segments.
     * @return float
     */
    private function total_duration(array $segments): float {
        $total = 0.0;
        foreach ($segments as $segment) {
            $total += max(0.0, (float)$segment[1] - (float)$segment[0]);
        }
        return $total;
    }

    /**
     * Checks whether a position falls inside a watched segment.
     *
     * @param array $segments Segments.
     * @param float $position Position.
     * @return bool
     */
    private function contains(array $segments, float $position): bool {
        foreach ($segments as $segment) {
            if ($position >= (float)$segment[0] - 0.5 && $position <= (float)$segment[1] + 0.5) {
                return true;
            }
        }
        return false;
    }
}
