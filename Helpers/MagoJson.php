<?php

declare(strict_types=1);

namespace Simbiat\QARunner\Helpers;

use Simbiat\QARunner\Entities\Finding;

/**
 * Unified processor of Mago's JSON report
 */
final class MagoJson
{
    /**
     * Convert the tool output to findings. Only the findings in the requested paths are kept.
     *
     * @param array  $data  Standard output of the tool
     * @param Paths  $paths Paths to be scanned
     * @param string $tool  Tool name
     *
     *
     * @return array<Finding>
     */
    public static function extractFindings(array $data, Paths $paths, string $tool): array
    {
        $findings = [];
        foreach ($data['issues'] ?? [] as $issue) {
            $file = $issue['annotations'][0]['span']['file_id']['path'];
            if (!$paths->covers((string) $file)) {
                continue;
            }

            $findings[] = new Finding(
                tool: $tool,
                rule: $issue['code'] ?? 'unknown',
                file: (string) $file,
                line: (int) $issue['annotations'][0]['span']['start']['line'],
                column: (int) $issue['annotations'][0]['span']['start']['column'],
                message: ($issue['message'] ?? ''),
                type: $issue['level'],
                fixable: \array_key_exists('edits', $issue) && $issue['edits'] !== [],
            );
        }

        return $findings;
    }
}
