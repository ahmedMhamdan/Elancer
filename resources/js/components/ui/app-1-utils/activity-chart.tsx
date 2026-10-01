'use client';

// Supplied App1 area-chart composition, using actual weekly proposals/contracts.
import { useId } from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { BarChart3 } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Table, TableHeader, TableBody, TableRow, TableCell, TableScroll } from '@/components/tailadmin/table';
import { useTranslation } from '@/hooks/use-translation';
import type { OverviewData } from '@/types/overview';

export function ActivityChart({ data, client }: { data: OverviewData['chart']; client: boolean }) {
    const { t, locale } = useTranslation();
    const id = 'overview-' + useId().replace(/:/g, '');
    const proposalsLabel = client ? t('Proposals received') : t('Proposals submitted');
    const contractsLabel = t('Contracts accepted');
    const config = { proposals: { label: proposalsLabel, color: 'var(--el-muted)' }, contracts: { label: contractsLabel, color: 'var(--el-accent)' } };
    const date = (week: string) => new Date(week + 'T00:00:00Z').toLocaleDateString(locale, { month: 'short', day: 'numeric', timeZone: 'UTC' });
    const rows = data.map((row) => ({ ...row, label: date(row.week) }));
    const hasActivity = data.some((row) => row.proposals > 0 || row.contracts > 0);
    return (
        <Card className="overview-card overview-chart-card">
            <CardHeader>
                <CardTitle><h2>{t('Workspace activity')}</h2></CardTitle>
                <CardDescription>{t('Submitted proposals and accepted contracts over the last eight weeks.')}</CardDescription>
            </CardHeader>
            <CardContent className="min-w-0">
                {hasActivity ? <ChartContainer config={config} className="overview-chart h-64 w-full" dir="ltr" aria-label={t('Weekly workspace activity')}>
                    <AreaChart data={rows} margin={{ left: 4, right: 8, top: 8 }} accessibilityLayer>
                        <defs><linearGradient id={id} x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="var(--color-contracts)" stopOpacity={0.25} /><stop offset="100%" stopColor="var(--color-contracts)" stopOpacity={0.02} /></linearGradient></defs>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} minTickGap={16} />
                        <YAxis tickLine={false} axisLine={false} width={28} allowDecimals={false} />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Area dataKey="proposals" type="monotone" stroke="var(--color-proposals)" strokeDasharray="4 4" fill="none" strokeWidth={2} isAnimationActive={false} />
                        <Area dataKey="contracts" type="monotone" stroke="var(--color-contracts)" fill={'url(#' + id + ')'} strokeWidth={2} isAnimationActive={false} />
                    </AreaChart>
                </ChartContainer> : <div className="overview-empty overview-chart-empty"><BarChart3 className="size-8" aria-hidden="true" /><h3 className="font-medium">{t('No activity in this period')}</h3><p>{t('Submitted proposals and accepted agreements will build your activity chart.')}</p></div>}
                <p className="text-muted-foreground mt-4 text-xs leading-relaxed">{t('Weeks start Monday (UTC). The current week is still in progress.')}</p>
                <details className="overview-chart-data mt-3">
                    <summary className="overview-text-link min-h-11">{t('View chart data')}</summary>
                    <TableScroll label={t('Weekly workspace activity')}>
                        <Table className="text-sm"><caption className="sr-only">{t('Weekly workspace activity')}</caption><TableHeader><TableRow><TableCell isHeader>{t('Week starting')}</TableCell><TableCell isHeader>{proposalsLabel}</TableCell><TableCell isHeader>{contractsLabel}</TableCell></TableRow></TableHeader><TableBody>{rows.map((row) => <TableRow key={row.week}><TableCell>{row.label}</TableCell><TableCell>{row.proposals.toLocaleString(locale)}</TableCell><TableCell>{row.contracts.toLocaleString(locale)}</TableCell></TableRow>)}</TableBody></Table>
                    </TableScroll>
                </details>
            </CardContent>
        </Card>
    );
}
