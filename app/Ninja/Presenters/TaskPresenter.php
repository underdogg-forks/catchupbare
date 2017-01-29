<?php namespace App\Ninja\Presenters;

/**
 * Class TaskPresenter
 */
class TaskPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function relation()
    {
        return $this->entity->relation ? $this->entity->relation->getDisplayName() : '';
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function description()
    {
        return substr($this->entity->description, 0, 40) . (strlen($this->entity->description) > 40 ? '...' : '');
    }

    public function project()
    {
        return $this->entity->project ? $this->entity->project->name : '';
    }

    /**
     * @param $company
     * @return mixed
     */
    public function invoiceDescription($company, $showProject)
    {
        $str = '';

        if ($showProject && $project = $this->project()) {
            $str .= "## {$project}\n\n";
        }

        if ($description = trim($this->entity->description)) {
            $str .= $description . "\n\n";
        }

        $parts = json_decode($this->entity->time_log) ?: [];
        $times = [];

        foreach ($parts as $part) {
            $start = $part[0];
            if (count($part) == 1 || !$part[1]) {
                $end = time();
            } else {
                $end = $part[1];
            }

            $start = $company->formatDateTime("@{$start}");
            $end = $company->formatTime("@{$end}");

            $times[] = "### {$start} - {$end}";
        }

        return $str . implode("\n", $times);
    }
}
