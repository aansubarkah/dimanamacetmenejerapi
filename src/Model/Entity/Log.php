<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Log Entity.
 *
 * @property int $id
 * @property int $user_id
 * @property \App\Model\Entity\User $user
 * @property string $controller
 * @property string $action
 * @property string $name
 * @property \Cake\I18n\Time $created
 * @property \Cake\I18n\Time $modified
 * @property bool $active
 */
class Log extends Entity
{

}
