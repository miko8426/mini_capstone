<?php

declare(strict_types=1);

/**
 * Step 6 appliance module tests.
 *
 * Uses an in-memory SQLite database so the inventory logic can be verified
 * without a running MySQL server. Run: php tests/appliance_inventory_test.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Appliance;
use App\Models\ApplianceCategory;
use App\Models\Organization;

$failures = 0;
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  PASS  {$label}\n";
        return;
    }
    $failures++;
    echo "  FAIL  {$label}\n";
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(<<<'SQL'
CREATE TABLE campuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT NOT NULL
);
CREATE TABLE offices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    campus_id INTEGER NOT NULL,
    room_number TEXT NOT NULL,
    name TEXT NOT NULL,
    floor_level INTEGER DEFAULT 1
);
CREATE TABLE appliance_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE appliances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    office_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    brand_model TEXT NULL,
    voltage DECIMAL(8,2) NOT NULL DEFAULT 220,
    wattage DECIMAL(10,2) NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    daily_operating_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
    usage_frequency TEXT NOT NULL DEFAULT 'daily',
    status TEXT DEFAULT 'active',
    archived_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL);

$pdo->exec("INSERT INTO campuses (id, name, code) VALUES (1, 'Main Campus', 'MC'), (2, 'Technology Campus', 'TC')");
$pdo->exec("INSERT INTO offices (id, campus_id, room_number, name, floor_level) VALUES
    (1, 1, 'SH-101', 'EE Lab', 1),
    (2, 1, 'ADM-201', 'Dean Office', 2),
    (3, 2, 'ITC-302', 'Computer Lab A', 3)");

$categories = new ApplianceCategory($pdo);
$appliances = new Appliance($pdo);
$organization = new Organization($pdo);

echo "Category management\n";
$catId = $categories->create('Air Conditioning', 'HVAC systems');
check('create returns an id', $catId > 0);
check('find returns the created category', ($categories->find($catId)['name'] ?? null) === 'Air Conditioning');
check('duplicate name detected', $categories->existsByName('Air Conditioning'));
check('duplicate check ignores own id on update', !$categories->existsByName('Air Conditioning', $catId));
$categories->update($catId, 'Air Conditioning & Cooling', 'HVAC');
check('update persists new name', ($categories->find($catId)['name'] ?? null) === 'Air Conditioning & Cooling');
check('search filter finds category', count($categories->paginate('Cooling')['items']) === 1);
$cat2 = $categories->create('Lighting', null);

echo "\nOffice-based assignment\n";
$office = $organization->findOffice(1);
check('existing office resolves', $office !== null);
check('office exposes campus id', (int) ($office['campus_id'] ?? 0) === 1);
check('missing office resolves to null', $organization->findOffice(999) === null);
check('offices list includes organization path', ($organization->offices()[0]['campus_name'] ?? '') !== '');

echo "\nAppliance creation\n";
$applianceId = $appliances->create([
    'category_id' => $catId,
    'office_id' => 1,
    'name' => 'Split AC 2HP',
    'brand_model' => 'Carrier Inverter',
    'voltage' => 220,
    'wattage' => 1800,
    'quantity' => 2,
    'daily_operating_hours' => 8,
    'usage_frequency' => 'daily',
    'status' => 'active',
]);
check('create returns an id', $applianceId > 0);
$created = $appliances->find($applianceId);
check('appliance is assigned to the office', (int) ($created['office_id'] ?? 0) === 1);
check('detail query resolves category name', ($created['category_name'] ?? '') === 'Air Conditioning & Cooling');
check('detail query resolves campus/office', ($created['campus_name'] ?? '') === 'Main Campus'
    && ($created['office_name'] ?? '') === 'EE Lab');

$secondId = $appliances->create([
    'category_id' => $cat2,
    'office_id' => 3,
    'name' => 'Desktop Workstation',
    'brand_model' => 'Dell OptiPlex',
    'voltage' => 220,
    'wattage' => 250,
    'quantity' => 30,
    'daily_operating_hours' => 9.5,
    'usage_frequency' => 'daily',
    'status' => 'under_maintenance',
]);
check('second appliance created in another office', $secondId > 0);

echo "\nEditing\n";
$updated = $appliances->update($secondId, [
    'category_id' => $cat2,
    'office_id' => 1,
    'name' => 'Desktop Workstation v2',
    'brand_model' => 'Dell OptiPlex 7090',
    'voltage' => 230,
    'wattage' => 300,
    'quantity' => 25,
    'daily_operating_hours' => 10,
    'usage_frequency' => 'weekly',
    'status' => 'active',
]);
$edited = $appliances->find($secondId);
check('update reports success', $updated);
check('name updated', ($edited['name'] ?? '') === 'Desktop Workstation v2');
check('office reassigned', (int) ($edited['office_id'] ?? 0) === 1);
check('status updated', ($edited['status'] ?? '') === 'active');
check('operating hours updated', (float) ($edited['daily_operating_hours'] ?? 0) === 10.0);

echo "\nSearch and filtering\n";
check('campus filter', $appliances->paginate(['campus_id' => 2])['total'] === 0);
check('campus filter with matches', $appliances->paginate(['campus_id' => 1])['total'] === 2);
check('office filter', $appliances->paginate(['office_id' => 3])['total'] === 0);
check('category filter', $appliances->paginate(['category_id' => $cat2])['total'] === 1);
check('search by name', $appliances->paginate(['search' => 'Split'])['total'] === 1);
check('search by office name', $appliances->paginate(['search' => 'EE Lab'])['total'] === 2);
check('status filter', $appliances->paginate(['status' => 'active'])['total'] === 2);

echo "\nStats and grouping\n";
$stats = $appliances->stats();
check('stats count matches', $stats['count'] === 2);
check('stats total quantity', $stats['total_quantity'] === 27);
check('stats estimated kWh/day is positive', $stats['kwh_per_day'] > 0);
check('summary by campus returns rows', count($appliances->summaryBy('campus')) === 1);
check('summary by office returns rows', count($appliances->summaryBy('office')) === 1);

echo "\nPagination\n";
for ($i = 1; $i <= 15; $i++) {
    $appliances->create([
        'category_id' => $cat2,
        'office_id' => 1,
        'name' => sprintf('Bulk Lamp %02d', $i),
        'brand_model' => '',
        'voltage' => 220,
        'wattage' => 40,
        'quantity' => 1,
        'daily_operating_hours' => 5,
        'usage_frequency' => 'daily',
        'status' => 'active',
    ]);
}
$page1 = $appliances->paginate([], 1, 10);
$page2 = $appliances->paginate([], 2, 10);
check('pagination total includes all records', $page1['total'] === 17);
check('first page holds per-page limit', count($page1['items']) === 10);
check('second page holds remainder', count($page2['items']) === 7);
check('page count is correct', $page1['pages'] === 2);
check('out-of-range page is clamped', $appliances->paginate([], 99, 10)['page'] === 2);

echo "\nStatus management\n";
check('valid status update', $appliances->updateStatus($applianceId, 'under_maintenance'));
check('status persisted', ($appliances->find($applianceId)['status'] ?? '') === 'under_maintenance');
check('invalid status rejected', !$appliances->updateStatus($applianceId, 'broken'));

echo "\nArchival\n";
check('archive succeeds', $appliances->archive($applianceId));
check('archived record excluded from default list', $appliances->paginate(['search' => 'Split AC'])['total'] === 0);
check('archived record found in archived filter', $appliances->paginate(['status' => 'archived'])['total'] === 1);
check('active category count drops when archived', $categories->applianceCount($catId) === 0);
check('category still referenced by archived appliance', $categories->referenceCount($catId) === 1);
check('archived record cannot change status', !$appliances->updateStatus($applianceId, 'active'));
check('restore succeeds', $appliances->restore($applianceId));
check('restored record visible again', $appliances->paginate(['search' => 'Split AC'])['total'] === 1);

echo "\nCategory deletion guard\n";
check('in-use category reports appliance count', $categories->applianceCount($cat2) > 0);
check('in-use category blocks deletion by reference count', $categories->referenceCount($cat2) > 0);
$unusedId = $categories->create('Temporary', null);
check('unused category count is zero', $categories->applianceCount($unusedId) === 0);
check('unused category can be deleted', $categories->delete($unusedId) && $categories->find($unusedId) === null);

echo "\n----------------------------------------\n";
echo "{$checks} checks, {$failures} failure(s)\n";
exit($failures === 0 ? 0 : 1);
