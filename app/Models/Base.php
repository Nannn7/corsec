<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Mattiverse\Userstamps\Traits\Userstamps;


/**
 *
 */
class Base extends Model
{
    use LogsActivity, SoftDeletes, Userstamps;

    protected $connection;

    /**
     * Constructs a new instance of the class.
     *
     * @param array $attributes Optional attributes to initialize the object with.
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Retrieve the module configuration from the module.json file
        $modulePath = dirname(__FILE__, 3) . '/module.json';
        $module     = file_get_contents($modulePath);
        $module     = json_decode($module);

        // Set the connection property to the database connection specified in the module configuration
        $this->connection = $module->database;
    }

    /**
     * Retrieves the activity log options for the User Management.
     *
     * @return LogOptions The activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->useLogName('Basic Data');
    }
}
