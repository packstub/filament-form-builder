<?php

namespace Packstub\FormBuilder\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Packstub\FormBuilder\Models\Form;

/**
 * Submissions per day on the form's edit page, over 7, 30 or 90 days, with
 * the totals in the description. Counted from the submissions table: no
 * tracking script, no views or starts.
 */
class SubmissionsChart extends ChartWidget
{
    public ?Model $record = null;

    public ?string $filter = '30';

    protected ?string $maxHeight = '180px';

    protected bool $isCollapsible = true;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return __('packstub-form-builder::form-builder.chart.heading');
    }

    public function getDescription(): ?string
    {
        $form = $this->form();

        if ($form === null) {
            return null;
        }

        return __('packstub-form-builder::form-builder.chart.totals', [
            'total' => $form->submissions()->count(),
            'week' => $form->submissions()->where('created_at', '>=', now()->subDays(7))->count(),
            'unread' => $form->submissions()->whereNull('read_at')->count(),
        ]);
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('packstub-form-builder::form-builder.chart.days', ['days' => 7]),
            '30' => __('packstub-form-builder::form-builder.chart.days', ['days' => 30]),
            '90' => __('packstub-form-builder::form-builder.chart.days', ['days' => 90]),
        ];
    }

    /**
     * @return array<string, int> Y-m-d => count, every day of the range.
     */
    public function perDay(): array
    {
        $form = $this->form();
        $days = in_array((int) $this->filter, [7, 30, 90], true) ? (int) $this->filter : 30;
        $start = now()->startOfDay()->subDays($days - 1);
        $counts = [];

        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $counts[$day->toDateString()] = 0;
        }

        if ($form === null) {
            return $counts;
        }

        $form->submissions()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$counts): void {
                $date = Carbon::parse($createdAt)->toDateString();

                if (array_key_exists($date, $counts)) {
                    $counts[$date]++;
                }
            });

        return $counts;
    }

    protected function getData(): array
    {
        $counts = $this->perDay();

        return [
            'datasets' => [[
                'label' => __('packstub-form-builder::form-builder.submissions.plural'),
                'data' => array_values($counts),
            ]],
            'labels' => array_map(fn (string $date): string => Carbon::parse($date)->translatedFormat('M j'), array_keys($counts)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    protected function form(): ?Form
    {
        return $this->record instanceof Form ? $this->record : null;
    }
}
