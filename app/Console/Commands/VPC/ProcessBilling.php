<?php

namespace App\Console\Commands\VPC;

use App\Models\V2\BillingMetric;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Class ProcessBilling
 * @package App\Console\Commands
 */
class ProcessBilling extends Command
{
    protected $signature = 'vpc:process-billing {--D|debug}';
    protected $description = 'Process eCloud VPC Billing';

    protected Carbon $startDate;
    protected Carbon $endDate;

    /**
     * Billable metrics - Add any metrics to this array that we want to bill for.
     * @var array|string[]
     */
    protected array $billableMetrics = [
        'vcpu.count',
        'ram.capacity',
        'disk.capacity',
        'license.windows'
    ];

    public function handle()
    {
        /** @var Instance $instance */
        $vpcs = Vpc::get();

        $this->startDate = Carbon::createFromTimeString("First day of last month 00:00:00");
        $this->endDate = Carbon::createFromTimeString("First day of this month 00:00:00");

        $this->info('VPC billing for period ' . $this->startDate . ' - ' . $this->endDate . PHP_EOL);


        $vpcs->each(function($vpc) {
            $metrics = $this->getVpcMetrics($vpc->id);

            if ($metrics->count() == 0) {
                return true;
            }

            $this->line('---------- ' . $vpc->id . ' ----------' . PHP_EOL);

            $billing = [];

            $metrics->keys()->each(function ($key) use (&$billing, $metrics) {
                if (!in_array($key, $this->billableMetrics)) {
                    return true;
                }

                if ($this->option('debug')) {
                    $this->line(PHP_EOL . 'Billing Metric: ' . $key . PHP_EOL);
                }

                $billing[$key] = 0;

                $metrics->get($key)->each(function($metric) use (&$billing, $key) {
                    //$this->info(print_r($metric->toArray()));

                    $start = $this->startDate;
                    $end = $this->endDate;

                    if ($metric->start > $this->startDate) {
                        $start = Carbon::parse($metric->start);
                    }

                    if (!empty($metric->end) && $metric->end < $this->endDate) {
                        $end = Carbon::parse($metric->end);
                    }

                    $hours = $start->diffInHours($end);

                    // Minimum period is 1 hour
                    $hours = ($hours < 1) ? 1 : $hours;

                    $cost = ($hours * $metric->price) * $metric->value;

                    if ($this->option('debug')) {
                        $this->info('metric: ' . $metric->id);
                        $this->info('resource_id: ' . $metric->resource_id);
                        $this->info('start: ' . $start);
                        $this->info('end: ' . $end);
                        $this->info('hours: ' . $hours);
                        $this->info('value / multiplier: ' . $metric->value);
                        $this->info('hourly price: £' . $metric->price);
                        $this->info('cost: £' . $cost . PHP_EOL);
                    }

                    $billing[$key] += $cost;
                });

                    $this->line($key . ': £' . number_format($billing[$key], 2));


            });

            $this->info(PHP_EOL);

            $total = array_sum($billing);

            $this->line('Total: £' . number_format($total, 2) . PHP_EOL);


            // Apply any discount plans








// min £1 surcharge





            // Push acc.log entries over to the billing apio the billing apio endpoint is: POST /v1/payments
            // instead of creating entries for each resource like v1 or collated resources like flex, we only require a single entry per vpc with the total cost
            // the description should be:  eCloud vpc-abc123de from 01/11/2020 to 30/11/2020
            // the category set to eCloud
            // the nominal code set to:  TBC
            // source set to: myukfast

        });
    }

    /**
     * Load the metrics for the VPC for the billing period
     * @param $vpcId
     * @return mixed
     */
    protected function getVpcMetrics($vpcId)
    {
        $metrics = BillingMetric::where('vpc_id', $vpcId)
            // any metrics that start before the billing period and end within it
            ->where(function ($query) {
                // any billing metrics for the vpc that start within the billing period
                $query->whereBetween('start', [$this->startDate, $this->endDate]);

                $query->orWhere('start', '<=', $this->startDate)
                    ->where('end', '<=', $this->endDate);

                // any metrics that start before the billing period are still active
                $query->orWhere(function ($query) {
                    $query->where('start', '<', $this->startDate)
                        ->whereNull('end');
                });

                $query->orWhere(function ($query) {
                        $query->where('start', '<', $this->startDate)
                            ->where('end',  '>', $this->endDate);
                });
            })
            ->get();

        return $metrics->mapToGroups(function ($item, $key) {
            return [$item['key'] => $item];
        });
    }
}
