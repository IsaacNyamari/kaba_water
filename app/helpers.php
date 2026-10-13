<?php


if (!function_exists('getPriorityDetails')) {
    /**
     * Get the icon and color classes for a given task priority.
     */
    function getPriorityDetails(string $priority): array
    {
        switch ($priority) {
            case 'high':
                return [
                    'icon'  => 'exclamation-triangle',
                    'color' => 'text-red-500 animate-pulse',
                ];

            case 'normal':
                return [
                    'icon'  => 'information-circle',
                    'color' => 'text-blue-500', // Added a default color for normal
                ];

            default:
                return [
                    'icon'  => 'question-mark-circle',
                    'color' => 'text-gray-500',
                ];
        }
    }
}

