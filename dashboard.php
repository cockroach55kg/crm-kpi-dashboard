<?php
/**
 * Дашборд активности отдела продаж
 *
 *
 * Что демонстрирует этот файл:
 * - пример интеграции с Bitrix24 / Bitrix Framework
 * - логику агрегации данных для CRM-дашборда
 * - разделение демо-данных и рабочего источника данных
 * - безопасную конфигурацию через переменные окружения
 *
 * Usage:
 * 1) Демо-режим для портфолио:
 *      php -S localhost:8080 public_portfolio_bitrix_dashboard.php
 *
 * 2) Рабочий режим внутри Bitrix-окружения:
 *      Set environment variables listed in BitrixDashboardConfig.
 *      Set DASHBOARD_DATA_SOURCE=bitrix.
 */

declare(strict_types=1);

const DASHBOARD_TITLE = 'Дашборд активности отдела продаж';
const DASHBOARD_DATA_SOURCE = 'demo'; // Для GitHub оставить "demo". В рабочей версии можно использовать getenv('DASHBOARD_DATA_SOURCE').

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Moscow');

/**
 * Escapes HTML for both Bitrix and standalone PHP environments.
 */
function e(string $value): string
{
    if (function_exists('htmlspecialcharsbx')) {
        return htmlspecialcharsbx($value);
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float $value): string
{
    return number_format($value, 0, ',', ' ');
}

function formatSecondsToHms(int $seconds): string
{
    return sprintf(
        '%02d:%02d:%02d',
        (int)floor($seconds / 3600),
        (int)floor(($seconds % 3600) / 60),
        $seconds % 60
    );
}

function parseEnvIntList(string $name, array $default = []): array
{
    $raw = getenv($name);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    return array_values(array_filter(array_map(
        static fn($item) => (int)trim($item),
        explode(',', $raw)
    )));
}

function parseEnvStringList(string $name, array $default = []): array
{
    $raw = getenv($name);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

final class BitrixDashboardConfig
{
    public array $departmentIds;
    public array $excludedUserIds;
    public array $crmCategoryIds;

    public string $ufResponsibleManager;
    public string $ufIncomingDate;
    public string $ufLeadSourceMarker;
    public string $ufKpApproveDate;
    public string $ufKpMargin;

    public string $incomingDealType;
    public string $coldDealType;
    public string $leadSourceMarkerValue;

    public function __construct()
    {
        $this->departmentIds = parseEnvIntList('B24_DASHBOARD_DEPARTMENT_IDS');
        $this->excludedUserIds = parseEnvIntList('B24_DASHBOARD_EXCLUDED_USER_IDS');
        $this->crmCategoryIds = parseEnvIntList('B24_DASHBOARD_CRM_CATEGORY_IDS');

        $this->ufResponsibleManager = getenv('B24_UF_RESPONSIBLE_MANAGER') ?: 'UF_CRM_RESPONSIBLE_MANAGER';
        $this->ufIncomingDate       = getenv('B24_UF_INCOMING_DATE') ?: 'UF_CRM_INCOMING_DATE';
        $this->ufLeadSourceMarker   = getenv('B24_UF_LEAD_SOURCE_MARKER') ?: 'UF_CRM_LEAD_SOURCE_MARKER';
        $this->ufKpApproveDate      = getenv('B24_UF_KP_APPROVE_DATE') ?: 'UF_CRM_KP_APPROVE_DATE';
        $this->ufKpMargin           = getenv('B24_UF_KP_MARGIN') ?: 'UF_CRM_KP_MARGIN';

        $this->incomingDealType = getenv('B24_INCOMING_DEAL_TYPE') ?: 'ВХОДЯЩИЙ';
        $this->coldDealType = getenv('B24_COLD_DEAL_TYPE') ?: 'ХОЛОДНЫЙ';
        $this->leadSourceMarkerValue = getenv('B24_LEAD_SOURCE_MARKER_VALUE') ?: 'МАРКЕР_ВХОДЯЩЕГО_ЛИДА';
    }

    public function validateForBitrixMode(): void
    {
        $missing = [];

        if (!$this->departmentIds) {
            $missing[] = 'B24_DASHBOARD_DEPARTMENT_IDS';
        }

        if (!$this->crmCategoryIds) {
            $missing[] = 'B24_DASHBOARD_CRM_CATEGORY_IDS';
        }

        if ($missing) {
            throw new RuntimeException(
                'Missing required environment variables for Bitrix mode: ' . implode(', ', $missing)
            );
        }
    }
}

interface DashboardDataProvider
{
    /**
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   totals: array<string, mixed>,
     *   meta: array<string, mixed>
     * }
     */
    public function getDashboardData(): array;
}

final class DemoDashboardDataProvider implements DashboardDataProvider
{
    public function getDashboardData(): array
    {
        $employees = [
            ['name' => 'Алексей Орлов',   'pair' => 'blue',   'leads' => 8,  'cold' => 3, 'tasks' => 14, 'kp' => 3, 'kpTurnover' => 420000, 'kpMargin' => 98000,  'calls' => 19, 'seconds' => 7420,  'monthTurnover' => 3250000, 'monthMargin' => 765000,  'yearTurnover' => 28600000, 'yearMargin' => 6380000],
            ['name' => 'Мария Соколова',   'pair' => 'blue',   'leads' => 6,  'cold' => 2, 'tasks' => 11, 'kp' => 2, 'kpTurnover' => 310000, 'kpMargin' => 71000,  'calls' => 17, 'seconds' => 6820,  'monthTurnover' => 2840000, 'monthMargin' => 634000,  'yearTurnover' => 24100000, 'yearMargin' => 5190000],
            ['name' => 'Никита Волков',   'pair' => 'red',    'leads' => 10, 'cold' => 4, 'tasks' => 16, 'kp' => 4, 'kpTurnover' => 690000, 'kpMargin' => 151000, 'calls' => 25, 'seconds' => 9130,  'monthTurnover' => 4120000, 'monthMargin' => 928000,  'yearTurnover' => 35100000, 'yearMargin' => 7990000],
            ['name' => 'София Белова',   'pair' => 'red',    'leads' => 5,  'cold' => 1, 'tasks' => 9,  'kp' => 2, 'kpTurnover' => 275000, 'kpMargin' => 64000,  'calls' => 13, 'seconds' => 5120,  'monthTurnover' => 1980000, 'monthMargin' => 462000,  'yearTurnover' => 20700000, 'yearMargin' => 4880000],
            ['name' => 'Даниил Морозов',  'pair' => 'purple', 'leads' => 7,  'cold' => 2, 'tasks' => 13, 'kp' => 3, 'kpTurnover' => 505000, 'kpMargin' => 119000, 'calls' => 21, 'seconds' => 8240,  'monthTurnover' => 3760000, 'monthMargin' => 872000,  'yearTurnover' => 31900000, 'yearMargin' => 7130000],
            ['name' => 'Анна Речина',   'pair' => 'purple', 'leads' => 4,  'cold' => 1, 'tasks' => 8,  'kp' => 1, 'kpTurnover' => 190000, 'kpMargin' => 43000,  'calls' => 12, 'seconds' => 4380,  'monthTurnover' => 2210000, 'monthMargin' => 501000,  'yearTurnover' => 18400000, 'yearMargin' => 4210000],
            ['name' => 'Иван Брунов',   'pair' => 'yellow', 'leads' => 9,  'cold' => 5, 'tasks' => 18, 'kp' => 4, 'kpTurnover' => 735000, 'kpMargin' => 172000, 'calls' => 28, 'seconds' => 10420, 'monthTurnover' => 4440000, 'monthMargin' => 1035000, 'yearTurnover' => 39800000, 'yearMargin' => 9340000],
            ['name' => 'Елена Лескова',   'pair' => 'yellow', 'leads' => 3,  'cold' => 1, 'tasks' => 7,  'kp' => 1, 'kpTurnover' => 150000, 'kpMargin' => 36000,  'calls' => 10, 'seconds' => 3970,  'monthTurnover' => 1760000, 'monthMargin' => 402000,  'yearTurnover' => 15900000, 'yearMargin' => 3660000],
            ['name' => 'Роман Долин',    'pair' => 'green',  'leads' => 6,  'cold' => 2, 'tasks' => 12, 'kp' => 2, 'kpTurnover' => 360000, 'kpMargin' => 84000,  'calls' => 16, 'seconds' => 6250,  'monthTurnover' => 2670000, 'monthMargin' => 621000,  'yearTurnover' => 22600000, 'yearMargin' => 5140000],
            ['name' => 'Екатерина Пальцева',   'pair' => 'green',  'leads' => 8,  'cold' => 3, 'tasks' => 15, 'kp' => 3, 'kpTurnover' => 455000, 'kpMargin' => 109000, 'calls' => 20, 'seconds' => 7760,  'monthTurnover' => 3090000, 'monthMargin' => 744000,  'yearTurnover' => 26900000, 'yearMargin' => 6420000],
            ['name' => 'Максим Карпов',    'pair' => 'gray',   'leads' => 5,  'cold' => 2, 'tasks' => 10, 'kp' => 2, 'kpTurnover' => 330000, 'kpMargin' => 79000,  'calls' => 15, 'seconds' => 5840,  'monthTurnover' => 2390000, 'monthMargin' => 571000,  'yearTurnover' => 21500000, 'yearMargin' => 4970000],
            ['name' => 'Ольга Сердцева',   'pair' => 'orange', 'leads' => 7,  'cold' => 3, 'tasks' => 13, 'kp' => 3, 'kpTurnover' => 485000, 'kpMargin' => 116000, 'calls' => 18, 'seconds' => 7010,  'monthTurnover' => 2980000, 'monthMargin' => 704000,  'yearTurnover' => 25400000, 'yearMargin' => 5920000],
        ];

        $rows = [];
        foreach ($employees as $employee) {
            $rows[] = [
                'NAME' => $employee['name'],
                'COLOR_CLASS' => 'name-' . $employee['pair'],
                'LEADS_IN_CNT' => $employee['leads'],
                'COLD_CNT' => $employee['cold'],
                'TODAY_TASKS_CNT' => $employee['tasks'],
                'KP_CNT' => $employee['kp'],
                'KP_TURNOVER' => (float)$employee['kpTurnover'],
                'KP_MARGIN' => (float)$employee['kpMargin'],
                'KP_MARGIN_PCT' => $employee['kpTurnover'] > 0 ? ($employee['kpMargin'] / $employee['kpTurnover']) * 100 : 0,
                'CALLS_CNT' => $employee['calls'],
                'CALLS_SECONDS' => $employee['seconds'],
                'CALLS_TIME_FMT' => formatSecondsToHms($employee['seconds']),
                'MONTH_TURNOVER' => (float)$employee['monthTurnover'],
                'MONTH_MARGIN' => (float)$employee['monthMargin'],
                'MONTH_MARGIN_PCT' => $employee['monthTurnover'] > 0 ? ($employee['monthMargin'] / $employee['monthTurnover']) * 100 : 0,
                'YEAR_TURNOVER' => (float)$employee['yearTurnover'],
                'YEAR_MARGIN' => (float)$employee['yearMargin'],
                'YEAR_MARGIN_PCT' => $employee['yearTurnover'] > 0 ? ($employee['yearMargin'] / $employee['yearTurnover']) * 100 : 0,
            ];
        }

        return [
            'rows' => $rows,
            'totals' => self::calculateИтогоs($rows),
            'meta' => [
                'source' => 'Синтетические демо-данные',
                'integration_note' => 'В рабочей версии используется источник данных Bitrix24 CRM. См. класс BitrixCrmDashboardDataProvider ниже.',
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public static function calculateИтогоs(array $rows): array
    {
        $totals = [
            'LEADS_IN_CNT' => 0,
            'COLD_CNT' => 0,
            'TODAY_TASKS_CNT' => 0,
            'KP_CNT' => 0,
            'KP_TURNOVER' => 0.0,
            'KP_MARGIN' => 0.0,
            'CALLS_CNT' => 0,
            'CALLS_SECONDS' => 0,
            'MONTH_TURNOVER' => 0.0,
            'MONTH_MARGIN' => 0.0,
            'YEAR_TURNOVER' => 0.0,
            'YEAR_MARGIN' => 0.0,
        ];

        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $row[$key] ?? 0;
            }
        }

        $totals['KP_MARGIN_PCT'] = $totals['KP_TURNOVER'] > 0
            ? ($totals['KP_MARGIN'] / $totals['KP_TURNOVER']) * 100
            : 0;

        $totals['MONTH_MARGIN_PCT'] = $totals['MONTH_TURNOVER'] > 0
            ? ($totals['MONTH_MARGIN'] / $totals['MONTH_TURNOVER']) * 100
            : 0;

        $totals['YEAR_MARGIN_PCT'] = $totals['YEAR_TURNOVER'] > 0
            ? ($totals['YEAR_MARGIN'] / $totals['YEAR_TURNOVER']) * 100
            : 0;

        $totals['CALLS_TIME_FMT'] = formatSecondsToHms((int)$totals['CALLS_SECONDS']);

        return $totals;
    }
}

/**
 * Очищенный Bitrix-источник данных.
 *
 * Этот класс намеренно использует плейсхолдеры и переменные окружения.
 * Он показывает паттерн интеграции без раскрытия рабочих ID,
 * имён сотрудников, кодов пользовательских полей и внутренних данных компании.
 */
final class BitrixCrmDashboardDataProvider implements DashboardDataProvider
{
    private BitrixDashboardConfig $config;

    public function __construct(BitrixDashboardConfig $config)
    {
        $this->config = $config;
        $this->config->validateForBitrixMode();
    }

    public function getDashboardData(): array
    {
        $this->bootstrapBitrix();

        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            throw new RuntimeException('Модуль CRM в Bitrix недоступен.');
        }

        $voximplantAvailable = \Bitrix\Main\Loader::includeModule('voximplant');

        $users = $this->loadActiveUsersFromDepartments();

        if (!$users) {
            return [
                'rows' => [],
                'totals' => DemoDashboardDataProvider::calculateИтогоs([]),
                'meta' => [
                    'source' => 'Bitrix24 CRM',
                    'integration_note' => 'В указанных отделах не найдены активные пользователи.',
                    'generated_at' => date('Y-m-d H:i:s'),
                ],
            ];
        }

        $this->loadIncomingЛиды($users);
        $this->loadColdЛиды($users);
        $this->loadTodayActivities($users);
        $this->loadKpПоказательs($users);
        $this->loadPeriodEfficiency($users, 'month');
        $this->loadPeriodEfficiency($users, 'year');

        if ($voximplantAvailable) {
            $this->loadCallПоказательs($users);
        }

        $rows = array_values($users);

        return [
            'rows' => $rows,
            'totals' => DemoDashboardDataProvider::calculateИтогоs($rows),
            'meta' => [
                'source' => 'Bitrix24 CRM',
                'integration_note' => 'Данные загружены через очищенный Bitrix-провайдер, настроенный через переменные окружения.',
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    private function bootstrapBitrix(): void
    {
        if (!defined('B_PROLOG_INCLUDED')) {
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? getenv('BITRIX_DOCUMENT_ROOT') ?: null;

            if (!$documentRoot || !is_file($documentRoot . '/bitrix/modules/main/include/prolog_before.php')) {
                throw new RuntimeException(
                    'Не найден bootstrap-файл Bitrix. Укажите BITRIX_DOCUMENT_ROOT или запускайте файл внутри Bitrix.'
                );
            }

            require_once $documentRoot . '/bitrix/modules/main/include/prolog_before.php';
        }
    }

    private function loadActiveUsersFromDepartments(): array
    {
        $users = [];

        $userList = \Bitrix\Main\UserTable::getList([
            'filter' => [
                'UF_DEPARTMENT' => $this->config->departmentIds,
                '=ACTIVE' => true,
            ],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'UF_DEPARTMENT'],
        ]);

        while ($user = $userList->fetch()) {
            $userId = (int)$user['ID'];

            if (in_array($userId, $this->config->excludedUserIds, true)) {
                continue;
            }

            $fullName = trim(($user['NAME'] ?: $user['LOGIN']) . ' ' . ($user['LAST_NAME'] ?? ''));

            $users[$userId] = [
                'USER_ID' => $userId,
                'NAME' => $fullName !== '' ? $fullName : ('Пользователь #' . $userId),
                'COLOR_CLASS' => 'report-cell',
                'LEADS_IN_CNT' => 0,
                'COLD_CNT' => 0,
                'TODAY_TASKS_CNT' => 0,
                'KP_CNT' => 0,
                'KP_TURNOVER' => 0.0,
                'KP_MARGIN' => 0.0,
                'KP_MARGIN_PCT' => 0.0,
                'CALLS_CNT' => 0,
                'CALLS_SECONDS' => 0,
                'CALLS_TIME_FMT' => '00:00:00',
                'MONTH_TURNOVER' => 0.0,
                'MONTH_MARGIN' => 0.0,
                'MONTH_MARGIN_PCT' => 0.0,
                'YEAR_TURNOVER' => 0.0,
                'YEAR_MARGIN' => 0.0,
                'YEAR_MARGIN_PCT' => 0.0,
            ];
        }

        return $users;
    }

    private function loadIncomingЛиды(array &$users): void
    {
        $this->loadLeadCount(
            $users,
            'LEADS_IN_CNT',
            "d.TYPE_ID = '{$this->sql($this->config->incomingDealType)}'
             OR u.{$this->safeIdentifier($this->config->ufLeadSourceMarker)} = '{$this->sql($this->config->leadSourceMarkerValue)}'"
        );
    }

    private function loadColdЛиды(array &$users): void
    {
        $this->loadLeadCount(
            $users,
            'COLD_CNT',
            "d.TYPE_ID = '{$this->sql($this->config->coldDealType)}'"
        );
    }

    private function loadLeadCount(array &$users, string $metricKey, string $typeCondition): void
    {
        $db = $GLOBALS['DB'];
        $userIdsSql = implode(',', array_map('intval', array_keys($users)));
        $categorySql = implode(',', array_map('intval', $this->config->crmCategoryIds));

        $from = $this->sql(date('Y-m-d 07:00:00'));
        $to = $this->sql(date('Y-m-d 00:00:00', strtotime('+1 day')));

        $responsibleField = $this->safeIdentifier($this->config->ufResponsibleManager);
        $incomingDateField = $this->safeIdentifier($this->config->ufIncomingDate);

        $sql = "
            SELECT u.{$responsibleField} AS UID, COUNT(*) AS CNT
            FROM b_crm_deal d
            INNER JOIN b_uts_crm_deal u ON u.VALUE_ID = d.ID
            WHERE d.CATEGORY_ID IN ({$categorySql})
              AND u.{$incomingDateField} >= '{$from}'
              AND u.{$incomingDateField} < '{$to}'
              AND u.{$responsibleField} IN ({$userIdsSql})
              AND ({$typeCondition})
            GROUP BY u.{$responsibleField}
        ";

        $result = $db->Query($sql);
        while ($row = $result->Fetch()) {
            $uid = (int)$row['UID'];
            if (isset($users[$uid])) {
                $users[$uid][$metricKey] = (int)$row['CNT'];
            }
        }
    }

    private function loadTodayActivities(array &$users): void
    {
        $db = $GLOBALS['DB'];
        $userIdsSql = implode(',', array_map('intval', array_keys($users)));
        $categorySql = implode(',', array_map('intval', $this->config->crmCategoryIds));
        $today = $this->sql(date('Y-m-d'));

        $sql = "
            SELECT d.ASSIGNED_BY_ID AS UID, COUNT(*) AS CNT
            FROM b_crm_deal d
            WHERE d.CATEGORY_ID IN ({$categorySql})
              AND d.STAGE_SEMANTIC_ID = 'P'
              AND d.ASSIGNED_BY_ID IN ({$userIdsSql})
              AND (
                    NOT EXISTS (
                        SELECT 1
                        FROM b_crm_act_bind ab
                        INNER JOIN b_crm_act a ON a.ID = ab.ACTIVITY_ID
                        WHERE ab.OWNER_TYPE_ID = 2
                          AND ab.OWNER_ID = d.ID
                          AND a.COMPLETED = 'N'
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM b_crm_act_bind ab
                        INNER JOIN b_crm_act a ON a.ID = ab.ACTIVITY_ID
                        WHERE ab.OWNER_TYPE_ID = 2
                          AND ab.OWNER_ID = d.ID
                          AND a.COMPLETED = 'N'
                          AND DATE(a.DEADLINE) <= '{$today}'
                    )
                  )
            GROUP BY d.ASSIGNED_BY_ID
        ";

        $result = $db->Query($sql);
        while ($row = $result->Fetch()) {
            $uid = (int)$row['UID'];
            if (isset($users[$uid])) {
                $users[$uid]['TODAY_TASKS_CNT'] = (int)$row['CNT'];
            }
        }
    }

    private function loadKpПоказательs(array &$users): void
    {
        $db = $GLOBALS['DB'];
        $userIdsSql = implode(',', array_map('intval', array_keys($users)));
        $categorySql = implode(',', array_map('intval', $this->config->crmCategoryIds));

        $from = $this->sql(date('Y-m-d 00:00:00'));
        $to = $this->sql(date('Y-m-d 00:00:00', strtotime('+1 day')));

        $responsibleField = $this->safeIdentifier($this->config->ufResponsibleManager);
        $approveDateField = $this->safeIdentifier($this->config->ufKpApproveDate);
        $marginField = $this->safeIdentifier($this->config->ufKpMargin);

        $sql = "
            SELECT
                u.{$responsibleField} AS UID,
                COUNT(*) AS CNT,
                SUM(d.OPPORTUNITY) AS TURNOVER,
                SUM(
                    CASE
                        WHEN u.{$marginField} IS NULL OR u.{$marginField} = ''
                            THEN 0
                        ELSE CAST(REPLACE(REPLACE(u.{$marginField}, ' ', ''), ',', '.') AS DECIMAL(18,2))
                    END
                ) AS MARGIN_SUM
            FROM b_crm_deal d
            INNER JOIN b_uts_crm_deal u ON u.VALUE_ID = d.ID
            WHERE d.CATEGORY_ID IN ({$categorySql})
              AND u.{$approveDateField} >= '{$from}'
              AND u.{$approveDateField} < '{$to}'
              AND u.{$responsibleField} IN ({$userIdsSql})
            GROUP BY u.{$responsibleField}
        ";

        $result = $db->Query($sql);
        while ($row = $result->Fetch()) {
            $uid = (int)$row['UID'];
            if (!isset($users[$uid])) {
                continue;
            }

            $turnover = (float)$row['TURNOVER'];
            $margin = (float)$row['MARGIN_SUM'];

            $users[$uid]['KP_CNT'] = (int)$row['CNT'];
            $users[$uid]['KP_TURNOVER'] = $turnover;
            $users[$uid]['KP_MARGIN'] = $margin;
            $users[$uid]['KP_MARGIN_PCT'] = $turnover > 0 ? ($margin / $turnover) * 100 : 0;
        }
    }

    private function loadPeriodEfficiency(array &$users, string $period): void
    {
        $db = $GLOBALS['DB'];
        $userIdsSql = implode(',', array_map('intval', array_keys($users)));
        $categorySql = implode(',', array_map('intval', $this->config->crmCategoryIds));

        $fromDate = $period === 'year'
            ? date('Y-01-01 00:00:00')
            : date('Y-m-01 00:00:00');

        $from = $this->sql($fromDate);
        $to = $this->sql(date('Y-m-d 00:00:00', strtotime('+1 day')));

        $responsibleField = $this->safeIdentifier($this->config->ufResponsibleManager);
        $approveDateField = $this->safeIdentifier($this->config->ufKpApproveDate);
        $marginField = $this->safeIdentifier($this->config->ufKpMargin);

        $sql = "
            SELECT
                u.{$responsibleField} AS UID,
                SUM(d.OPPORTUNITY) AS TURNOVER,
                SUM(
                    CASE
                        WHEN u.{$marginField} IS NULL OR u.{$marginField} = ''
                            THEN 0
                        ELSE CAST(REPLACE(REPLACE(u.{$marginField}, ' ', ''), ',', '.') AS DECIMAL(18,2))
                    END
                ) AS MARGIN_SUM
            FROM b_crm_deal d
            INNER JOIN b_uts_crm_deal u ON u.VALUE_ID = d.ID
            WHERE d.CATEGORY_ID IN ({$categorySql})
              AND u.{$approveDateField} >= '{$from}'
              AND u.{$approveDateField} < '{$to}'
              AND u.{$responsibleField} IN ({$userIdsSql})
            GROUP BY u.{$responsibleField}
        ";

        $turnoverKey = $period === 'year' ? 'YEAR_TURNOVER' : 'MONTH_TURNOVER';
        $marginKey = $period === 'year' ? 'YEAR_MARGIN' : 'MONTH_MARGIN';
        $percentKey = $period === 'year' ? 'YEAR_MARGIN_PCT' : 'MONTH_MARGIN_PCT';

        $result = $db->Query($sql);
        while ($row = $result->Fetch()) {
            $uid = (int)$row['UID'];
            if (!isset($users[$uid])) {
                continue;
            }

            $turnover = (float)$row['TURNOVER'];
            $margin = (float)$row['MARGIN_SUM'];

            $users[$uid][$turnoverKey] = $turnover;
            $users[$uid][$marginKey] = $margin;
            $users[$uid][$percentKey] = $turnover > 0 ? ($margin / $turnover) * 100 : 0;
        }
    }

    private function loadCallПоказательs(array &$users): void
    {
        $db = $GLOBALS['DB'];
        $userIdsSql = implode(',', array_map('intval', array_keys($users)));
        $from = $this->sql(date('Y-m-d 00:00:00'));

        $sql = "
            SELECT PORTAL_USER_ID AS UID, COUNT(*) AS CALLS, SUM(CALL_DURATION) AS SECS
            FROM b_voximplant_statistic
            WHERE CALL_START_DATE >= '{$from}'
              AND PORTAL_USER_ID IN ({$userIdsSql})
              AND CALL_DURATION >= 10
            GROUP BY PORTAL_USER_ID
        ";

        $result = $db->Query($sql);
        while ($row = $result->Fetch()) {
            $uid = (int)$row['UID'];
            if (!isset($users[$uid])) {
                continue;
            }

            $seconds = (int)$row['SECS'];
            $users[$uid]['CALLS_CNT'] = (int)$row['CALLS'];
            $users[$uid]['CALLS_SECONDS'] = $seconds;
            $users[$uid]['CALLS_TIME_FMT'] = formatSecondsToHms($seconds);
        }
    }

    private function sql(string $value): string
    {
        return $GLOBALS['DB']->ForSql($value);
    }

    private function safeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe SQL identifier in Bitrix dashboard config.');
        }

        return $identifier;
    }
}

function createProvider(): DashboardDataProvider
{
    $source = getenv('DASHBOARD_DATA_SOURCE') ?: DASHBOARD_DATA_SOURCE;

    if ($source === 'bitrix') {
        return new BitrixCrmDashboardDataProvider(new BitrixDashboardConfig());
    }

    return new DemoDashboardDataProvider();
}

function renderПоказательRow(string $label, string $total, array $rows, callable $cellFormatter): void
{
    echo '<tr>';
    echo '<td class="sticky left-0 report-sticky border">' . e($label) . '</td>';
    echo '<td class="report-total border">' . $total . '</td>';

    foreach ($rows as $row) {
        echo '<td class="' . e((string)$row['COLOR_CLASS']) . ' border">' . $cellFormatter($row) . '</td>';
    }

    echo '</tr>';
}

try {
    $data = createProvider()->getDashboardData();
    $rows = $data['rows'];
    $totals = $data['totals'];
    $meta = $data['meta'];
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<h1>Ошибка дашборда</h1>';
    echo '<p>' . e($exception->getMessage()) . '</p>';
    exit;
}

$theme = [
    'page_bg' => '#dfe3e8',
    'table_bg' => '#ffffff',
    'header_bg' => '#111a24',
    'header_text' => '#ffffff',
    'section_bg' => '#bcc8d6',
    'section_text' => '#16212c',
    'sticky_bg' => '#eef2f6',
    'sticky_text' => '#1e2a35',
    'total_bg' => '#d7e1eb',
    'total_text' => '#14202b',
    'cell_bg' => '#ffffff',
    'cell_text' => '#1d2935',
    'border' => '#b9c5d1',
    'shadow' => '0 16px 34px rgba(17,26,36,.18)',
    'name_blue' => '#d7e6ff',
    'name_red' => '#f7d9df',
    'name_purple' => '#e7dcfb',
    'name_green' => '#d9efdf',
    'name_yellow' => '#f7edc9',
    'name_gray' => '#dde3e9',
    'name_orange' => '#f8e1cc',
];

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e(DASHBOARD_TITLE) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --page-bg: <?= e($theme['page_bg']) ?>;
            --table-bg: <?= e($theme['table_bg']) ?>;
            --header-bg: <?= e($theme['header_bg']) ?>;
            --header-text: <?= e($theme['header_text']) ?>;
            --section-bg: <?= e($theme['section_bg']) ?>;
            --section-text: <?= e($theme['section_text']) ?>;
            --sticky-bg: <?= e($theme['sticky_bg']) ?>;
            --sticky-text: <?= e($theme['sticky_text']) ?>;
            --total-bg: <?= e($theme['total_bg']) ?>;
            --total-text: <?= e($theme['total_text']) ?>;
            --cell-bg: <?= e($theme['cell_bg']) ?>;
            --cell-text: <?= e($theme['cell_text']) ?>;
            --border-color: <?= e($theme['border']) ?>;
            --table-shadow: <?= e($theme['shadow']) ?>;
            --name-blue: <?= e($theme['name_blue']) ?>;
            --name-red: <?= e($theme['name_red']) ?>;
            --name-purple: <?= e($theme['name_purple']) ?>;
            --name-green: <?= e($theme['name_green']) ?>;
            --name-yellow: <?= e($theme['name_yellow']) ?>;
            --name-gray: <?= e($theme['name_gray']) ?>;
            --name-orange: <?= e($theme['name_orange']) ?>;
        }

        html, body {
            width: 100%;
            min-height: 100%;
            margin: 0;
            padding: 0;
            background: var(--page-bg);
            overflow-x: hidden;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .report-page {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
        }

        .portfolio-note {
            margin: 0 0 12px;
            padding: 12px 16px;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: var(--table-shadow);
            color: #1d2935;
            font-size: 14px;
        }

        .portfolio-note strong {
            font-weight: 700;
        }

        .report-table-wrap {
            width: 100%;
            overflow: hidden;
            border-radius: 18px;
            background: var(--table-bg);
            box-shadow: var(--table-shadow);
        }

        .report-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--table-bg);
        }

        .report-table td {
            border-color: var(--border-color) !important;
            color: var(--cell-text);
            font-size: clamp(10px, 0.62vw, 13px);
            line-height: 1.25;
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
            box-sizing: border-box;
        }

        .report-header-main {
            background: var(--header-bg) !important;
            color: var(--header-text) !important;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .report-section {
            background: var(--section-bg) !important;
            color: var(--section-text) !important;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .report-sticky {
            background: var(--sticky-bg) !important;
            color: var(--sticky-text) !important;
            font-weight: 600;
        }

        .report-total {
            background: var(--total-bg) !important;
            color: var(--total-text) !important;
            font-weight: 700;
        }

        .report-cell { background: var(--cell-bg) !important; }
        .name-blue { background: var(--name-blue) !important; }
        .name-red { background: var(--name-red) !important; }
        .name-purple { background: var(--name-purple) !important; }
        .name-green { background: var(--name-green) !important; }
        .name-yellow { background: var(--name-yellow) !important; }
        .name-gray { background: var(--name-gray) !important; }
        .name-orange { background: var(--name-orange) !important; }

        .report-table tr:hover td.report-cell,
        .report-table tr:hover td.name-blue,
        .report-table tr:hover td.name-red,
        .report-table tr:hover td.name-purple,
        .report-table tr:hover td.name-green,
        .report-table tr:hover td.name-yellow,
        .report-table tr:hover td.name-gray,
        .report-table tr:hover td.name-orange {
            filter: brightness(0.96) saturate(1.08);
        }

        .report-col-title {
            width: 15%;
        }

        .report-col-total {
            width: 8%;
        }

        @media (max-width: 1200px) {
            .report-table td {
                font-size: 9px;
                padding: 7px 5px;
            }

            .report-col-title {
                width: 18%;
            }

            .report-col-total {
                width: 8%;
            }
        }
    </style>

    <script>
        setTimeout(() => {
            console.log('Автообновление дашборда');
            location.reload();
        }, 5 * 60 * 1000);
    </script>
</head>
<body>
<div class="report-page">
    <div class="portfolio-note">
        <strong>Безопасное демо для портфолио.</strong>
        Источник данных: <?= e((string)$meta['source']) ?>.
        <?= e((string)$meta['integration_note']) ?>
        Сформировано: <?= e((string)$meta['generated_at']) ?>.
    </div>

    <div class="report-table-wrap">
        <table class="report-table">
            <tbody>
                <tr>
                    <td class="sticky left-0 report-header-main report-col-title border">Показатель</td>
                    <td class="report-header-main report-col-total border">Итого</td>

                    <?php foreach ($rows as $row): ?>
                        <td class="<?= e((string)$row['COLOR_CLASS']) ?> font-semibold border">
                            <?= e((string)$row['NAME']) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Лиды</td>
                </tr>

                <?php renderПоказательRow('Входящие лиды, шт', (string)(int)$totals['LEADS_IN_CNT'], $rows, static fn($row) => (string)(int)$row['LEADS_IN_CNT']); ?>
                <?php renderПоказательRow('Холодные лиды, шт', (string)(int)$totals['COLD_CNT'], $rows, static fn($row) => (string)(int)$row['COLD_CNT']); ?>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Работа по базе</td>
                </tr>

                <?php renderПоказательRow('Дела на сегодня', (string)(int)$totals['TODAY_TASKS_CNT'], $rows, static fn($row) => (string)(int)$row['TODAY_TASKS_CNT']); ?>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Коммерческие предложения</td>
                </tr>

                <?php renderПоказательRow('Количество, шт', (string)(int)$totals['KP_CNT'], $rows, static fn($row) => (string)(int)$row['KP_CNT']); ?>
                <?php renderПоказательRow('Оборот, ₽', formatMoney((float)$totals['KP_TURNOVER']), $rows, static fn($row) => formatMoney((float)$row['KP_TURNOVER'])); ?>
                <?php renderПоказательRow('Маржа, ₽', formatMoney((float)$totals['KP_MARGIN']), $rows, static fn($row) => formatMoney((float)$row['KP_MARGIN'])); ?>
                <?php renderПоказательRow('Маржинальность, %', round((float)$totals['KP_MARGIN_PCT']) . '%', $rows, static fn($row) => round((float)$row['KP_MARGIN_PCT']) . '%'); ?>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Эффективность за месяц</td>
                </tr>

                <?php renderПоказательRow('Оборот за месяц, ₽', formatMoney((float)$totals['MONTH_TURNOVER']), $rows, static fn($row) => formatMoney((float)$row['MONTH_TURNOVER'])); ?>
                <?php renderПоказательRow('Маржа за месяц, ₽', formatMoney((float)$totals['MONTH_MARGIN']), $rows, static fn($row) => formatMoney((float)$row['MONTH_MARGIN'])); ?>
                <?php renderПоказательRow('Маржинальность за месяц, %', round((float)$totals['MONTH_MARGIN_PCT']) . '%', $rows, static fn($row) => round((float)$row['MONTH_MARGIN_PCT']) . '%'); ?>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Эффективность за год</td>
                </tr>

                <?php renderПоказательRow('Оборот за год, ₽', formatMoney((float)$totals['YEAR_TURNOVER']), $rows, static fn($row) => formatMoney((float)$row['YEAR_TURNOVER'])); ?>
                <?php renderПоказательRow('Маржа за год, ₽', formatMoney((float)$totals['YEAR_MARGIN']), $rows, static fn($row) => formatMoney((float)$row['YEAR_MARGIN'])); ?>
                <?php renderПоказательRow('Маржинальность за год, %', round((float)$totals['YEAR_MARGIN_PCT']) . '%', $rows, static fn($row) => round((float)$row['YEAR_MARGIN_PCT']) . '%'); ?>

                <tr>
                    <td colspan="<?= count($rows) + 2 ?>" class="report-section px-4 py-3 border text-left">Звонки</td>
                </tr>

                <?php renderПоказательRow('Звонки, pcs', (string)(int)$totals['CALLS_CNT'], $rows, static fn($row) => (string)(int)$row['CALLS_CNT']); ?>
                <?php renderПоказательRow('Итого talk time', e((string)$totals['CALLS_TIME_FMT']), $rows, static fn($row) => e((string)$row['CALLS_TIME_FMT'])); ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
