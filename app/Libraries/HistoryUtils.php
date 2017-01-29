<?php namespace App\Libraries;

use Request;
use stdClass;
use Session;
use App\Models\EntityModel;
use App\Models\Activity;

class HistoryUtils
{
    public static function loadHistory($users)
    {
        $userIds = [];

        if (is_array($users)) {
            foreach ($users as $user) {
                $userIds[] = $user->user_id;
            }
        } else {
            $userIds[] = $users;
        }

        $activityTypes = [
            ACTIVITY_TYPE_CREATE_RELATION,
            ACTIVITY_TYPE_CREATE_TASK,
            ACTIVITY_TYPE_UPDATE_TASK,
            ACTIVITY_TYPE_CREATE_EXPENSE,
            ACTIVITY_TYPE_UPDATE_EXPENSE,
            ACTIVITY_TYPE_CREATE_INVOICE,
            ACTIVITY_TYPE_UPDATE_INVOICE,
            ACTIVITY_TYPE_EMAIL_INVOICE,
            ACTIVITY_TYPE_CREATE_QUOTE,
            ACTIVITY_TYPE_UPDATE_QUOTE,
            ACTIVITY_TYPE_EMAIL_QUOTE,
            ACTIVITY_TYPE_VIEW_INVOICE,
            ACTIVITY_TYPE_VIEW_QUOTE,
        ];

        $activities = Activity::with(['relation.contacts', 'invoice', 'task', 'expense'])
            ->whereIn('user_id', $userIds)
            ->whereIn('activity_type_id', $activityTypes)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        foreach ($activities->reverse() as $activity)
        {
            if ($activity->activity_type_id == ACTIVITY_TYPE_CREATE_RELATION) {
                $entity = $activity->relation;
            } else if ($activity->activity_type_id == ACTIVITY_TYPE_CREATE_TASK || $activity->activity_type_id == ACTIVITY_TYPE_UPDATE_TASK) {
                $entity = $activity->task;
                if ( ! $entity) {
                    continue;
                }
                $entity->setRelation('relation', $activity->relation);
            } else if ($activity->activity_type_id == ACTIVITY_TYPE_CREATE_EXPENSE || $activity->activity_type_id == ACTIVITY_TYPE_UPDATE_EXPENSE) {
                $entity = $activity->expense;
                if ( ! $entity) {
                    continue;
                }
                $entity->setRelation('relation', $activity->relation);
            } else {
                $entity = $activity->invoice;
                if ( ! $entity) {
                    continue;
                }
                $entity->setRelation('relation', $activity->relation);
            }

            static::trackViewed($entity);
        }
    }

    public static function trackViewed(EntityModel $entity)
    {
        $entityType = $entity->getEntityType();
        $trackedTypes = [
            ENTITY_RELATION,
            ENTITY_INVOICE,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE
        ];

        if ( ! in_array($entityType, $trackedTypes)) {
            return;
        }

        $object =  static::convertToObject($entity);
        $history = Session::get(RECENTLY_VIEWED) ?: [];
        $companyHistory = isset($history[$entity->company_id]) ? $history[$entity->company_id] : [];
        $data = [];

        // Add to the list and make sure to only show each item once
        /*for ($i = 0; $i<count($companyHistory); $i++) {
            $item = $companyHistory[$i];

            if ($object->url == $item->url) {
                continue;
            }

            array_push($data, $item);

            /*if (isset($counts[$item->accountId])) {
                    $counts[$item->accountId]++;
                } else {
                    $counts[$item->accountId] = 1;
                }*  /
          }*/

        array_unshift($data, $object);

        if (isset($counts[$entity->company_id]) && $counts[$entity->company_id] > RECENTLY_VIEWED_LIMIT) {
            array_pop($data);
        }

        $history[$entity->company_id] = $data;

        Session::put(RECENTLY_VIEWED, $history);
    }

    private static function convertToObject($entity)
    {
        $object = new stdClass();
        $object->companyId = $entity->company_id;
        $object->url = $entity->present()->url;
        $object->entityType = $entity->subEntityType();
        $object->name = $entity->present()->titledName;
        $object->timestamp = time();

        if ($entity->isEntityType(ENTITY_RELATION)) {
            $object->relation_id = $entity->public_id;
            $object->relation_name = $entity->getDisplayName();
        } elseif (method_exists($entity, 'relation') && $entity->relation) {
            $object->relation_id = $entity->relation->id;
            $object->relation_name = $entity->relation->getDisplayName();
        } else {
            $object->relation_id = 0;
            $object->relation_name = 0;
        }

        return $object;
    }

    public static function renderHtml($companyId)
    {
        $lastRelationId = false;
        $relationMap = [];
        $str = '';

        $history = Session::get(RECENTLY_VIEWED, []);
        $history = isset($history[$companyId]) ? $history[$companyId] : [];

        /*foreach ($history as $item)
        {
            if ($item->entityType == ENTITY_RELATION && isset($relationMap[$item->relation_id])) {
                continue;
            }

            $relationMap[$item->relation_id] = true;

            if ($lastRelationId === false || $item->relation_id != $lastRelationId)
            {
                $icon = '<i class="fa fa-users" style="width:32px"></i>';
                if ($item->relation_id) {
                    $link = url('/relations/' . $item->relation_id);
                    $name = $item->relation_name ;

                    $buttonLink = url('/invoices/create/' . $item->relation_id);
                    $button = '<a type="button" class="btn btn-primary btn-sm pull-right" href="' . $buttonLink . '">
                                    <i class="fa fa-plus-circle" style="width:20px" title="' . trans('texts.create_invoice') . '"></i>
                                </a>';
                } else {
                    $link = '#';
                    $name = trans('texts.unassigned');
                    $button = '';
                }

                $str .= sprintf('<li>%s<a href="%s"><div>%s %s</div></a></li>', $button, $link, $icon, $name);
                $lastRelationId = $item->relation_id;
            }

            if ($item->entityType == ENTITY_RELATION) {
                continue;
            }

            $icon = '<i class="fa fa-' . EntityModel::getIcon($item->entityType . 's') . '" style="width:24px"></i>';
            $str .= sprintf('<li style="text-align:right; padding-right:18px;"><a href="%s">%s %s</a></li>', $item->url, $item->name, $icon);
        }*/

        return $str;
    }
}
