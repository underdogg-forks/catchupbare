<?php

namespace App\Ninja\Reports;

use Auth;
use Utils;
use App\Models\Task;

class TaskReport extends AbstractReport
{
    public $columns = [
        'relation',
        'date',
        'project',
        'description',
        'duration',
    ];

    public function run()
    {
        $tasks = Task::scope()
                    ->with('relation.contacts')
                    ->withArchived()
                    ->dateRange($this->startDate, $this->endDate);

        foreach ($tasks->get() as $task) {
            $this->data[] = [
                $task->relation ? ($this->isExport ? $task->relation->getDisplayName() : $task->relation->present()->link) : trans('texts.unassigned'),
                link_to($task->present()->url, $task->getStartTime()),
                $task->present()->project,
                $task->present()->description,
                Utils::formatTime($task->getDuration()),
            ];
        }
    }
}
