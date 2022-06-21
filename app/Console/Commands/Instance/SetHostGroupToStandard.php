<?php

namespace App\Console\Commands\Instance;

use App\Console\Commands\Command;
use App\Models\V2\HostGroup;
use App\Models\V2\Instance;

class SetHostGroupToStandard extends Command
{
    protected $signature = 'instance:set-hostgroup {--T|test-run}';
    protected $description = 'Sets unassigned instances to standard hostgroup';

    public function handle()
    {
        Instance::where(function ($query) {
            $query->whereNull('host_group_id');
            $query->orWhere('host_group_id', '=', '');
        })->each(function (Instance $instance) {
            $hostGroupId = $instance->getHostGroupId();
            $hostGroup = HostGroup::find($instance->getHostGroupId());
            if (!$hostGroup) {
                $this->info('Creating hostgroup `' . $hostGroupId . '`');
                if (!$this->option('test-run')) {
                    $hostGroup = new HostGroup([
                        'id' => $hostGroupId,
                        'vpc_id' => $instance->vpc->id,
                        'availability_zone_id' => $instance->availabilityZone->id,
                        'host_spec_id' => 'hs-test', // <--- need to determine this
                        'windows_enabled' => !($instance->platform == 'Linux'),
                    ]);
                    $hostGroup->save();
                }
            }
            // Now we have a hostgroup, we need to attach it to the instance
            $this->info('Assigning hostgroup ' . $hostGroupId . ' to ' . $instance->id);
            if (!$this->option('test-run')) {
                $instance->hostGroup()->associate($hostGroup);
                $instance->save();
            }
        });
    }
}
