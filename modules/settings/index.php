<?php
// modules/settings/index.php - Systeminnstillinger administrasjonsside
require_once '../../config.php';

// Sjekk om brukeren er logget inn og har admin-rettigheter
if (!isLoggedIn() || $_SESSION['role_name'] !== 'admin') {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

// Side tittel
$pageTitle = "System Settings";

// Håndter skjemainnsending
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $updateCount = 0;
        
        // Opprett et array med innstillinger som kan redigeres
        $editableSettings = [
            'currency_symbol', 
            'currency_position', 
            'decimal_separator', 
            'thousands_separator',
            'app_name',
            'company_name',
            'company_email',
            'date_format',
            'time_format'
        ];
        
        // Oppdater hver innstilling som er sendt inn
        foreach ($editableSettings as $setting) {
            if (isset($_POST[$setting])) {
                $value = sanitizeInput($_POST[$setting]);
                
                // Spesiell validering for spesifikke innstillinger
                if ($setting === 'currency_position' && !in_array($value, ['before', 'after'])) {
                    $error = "Invalid currency position value.";
                    break;
                }
                
                if (saveSetting($setting, $value)) {
                    $updateCount++;
                }
            }
        }
        
        if (empty($error) && $updateCount > 0) {
            $success = "Settings updated successfully.";
        } elseif (empty($error)) {
            $error = "No settings were changed.";
        }
    }
}

// Hent alle innstillinger
$settings = getAllSettings(false);

// Inkluder header
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>System Settings</h2>
        <div class="card-header-actions">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn btn-text">
                <span class="material-icons">arrow_back</span> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Generelle innstillinger -->
            <div class="settings-section">
                <h3>General</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="app_name">Application Name</label>
                            <input type="text" id="app_name" name="app_name" class="form-control" 
                                   value="<?php echo $settings['app_name']['value'] ?? APP_NAME; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="form-control"
                                   value="<?php echo $settings['company_name']['value'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="company_email">Company Email</label>
                            <input type="email" id="company_email" name="company_email" class="form-control"
                                   value="<?php echo $settings['company_email']['value'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Valutainnstillinger -->
            <div class="settings-section">
                <h3>Currency Settings</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" class="form-control"
                                   value="<?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?>">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="currency_position">Currency Position</label>
                            <select id="currency_position" name="currency_position" class="form-control">
                                <option value="before" <?php echo ($settings['currency_position']['value'] ?? 'after') === 'before' ? 'selected' : ''; ?>>
                                    Before (<?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?> 100)
                                </option>
                                <option value="after" <?php echo ($settings['currency_position']['value'] ?? 'after') === 'after' ? 'selected' : ''; ?>>
                                    After (100 <?php echo $settings['currency_symbol']['value'] ?? 'NOK'; ?>)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="decimal_separator">Decimal Separator</label>
                            <select id="decimal_separator" name="decimal_separator" class="form-control">
                                <option value="." <?php echo ($settings['decimal_separator']['value'] ?? ',') === '.' ? 'selected' : ''; ?>>
                                    Period (.)
                                </option>
                                <option value="," <?php echo ($settings['decimal_separator']['value'] ?? ',') === ',' ? 'selected' : ''; ?>>
                                    Comma (,)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="thousands_separator">Thousands Separator</label>
                            <select id="thousands_separator" name="thousands_separator" class="form-control">
                                <option value=" " <?php echo ($settings['thousands_separator']['value'] ?? ' ') === ' ' ? 'selected' : ''; ?>>
                                    Space ( )
                                </option>
                                <option value="," <?php echo ($settings['thousands_separator']['value'] ?? ' ') === ',' ? 'selected' : ''; ?>>
                                    Comma (,)
                                </option>
                                <option value="." <?php echo ($settings['thousands_separator']['value'] ?? ' ') === '.' ? 'selected' : ''; ?>>
                                    Period (.)
                                </option>
                                <option value="" <?php echo ($settings['thousands_separator']['value'] ?? ' ') === '' ? 'selected' : ''; ?>>
                                    None
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="currency-preview">
                        <label>Preview:</label>
                        <div class="preview-box" id="currencyPreview">
                            <?php echo formatCurrency(1234.56); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dato og tid innstillinger -->
            <div class="settings-section">
                <h3>Date & Time Settings</h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="date_format">Date Format</label>
                            <select id="date_format" name="date_format" class="form-control">
                                <option value="d.m.Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'd.m.Y' ? 'selected' : ''; ?>>
                                    31.12.2025 (DD.MM.YYYY)
                                </option>
                                <option value="d/m/Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'd/m/Y' ? 'selected' : ''; ?>>
                                    31/12/2025 (DD/MM/YYYY)
                                </option>
                                <option value="m/d/Y" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'm/d/Y' ? 'selected' : ''; ?>>
                                    12/31/2025 (MM/DD/YYYY)
                                </option>
                                <option value="Y-m-d" <?php echo ($settings['date_format']['value'] ?? 'd.m.Y') === 'Y-m-d' ? 'selected' : ''; ?>>
                                    2025-12-31 (YYYY-MM-DD)
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="time_format">Time Format</label>
                            <select id="time_format" name="time_format" class="form-control">
                                <option value="H:i" <?php echo ($settings['time_format']['value'] ?? 'H:i') === 'H:i' ? 'selected' : ''; ?>>
                                    14:30 (24 hour)
                                </option>
                                <option value="h:i A" <?php echo ($settings['time_format']['value'] ?? 'H:i') === 'h:i A' ? 'selected' : ''; ?>>
                                    02:30 PM (12 hour)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="date-preview">
                        <label>Preview:</label>
                        <div class="preview-box" id="dateTimePreview">
                            <?php echo formatDateTime(date('Y-m-d H:i:s')); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="reset" class="btn btn-text">Reset</button>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<style>
.settings-section {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--grey-200);
}

.settings-section h3 {
    margin-bottom: var(--spacing-md);
    color: var(--grey-800);
    font-size: var(--font-size-xl);
}

.preview-box {
    background-color: var(--grey-50);
    padding: var(--spacing-md);
    border-radius: var(--border-radius-md);
    margin-top: var(--spacing-xs);
    font-weight: var(--font-weight-medium);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Oppdater valutaforhåndsvisning når innstillinger endres
    const currencySymbolInput = document.getElementById('currency_symbol');
    const currencyPositionSelect = document.getElementById('currency_position');
    const decimalSeparatorSelect = document.getElementById('decimal_separator');
    const thousandsSeparatorSelect = document.getElementById('thousands_separator');
    const currencyPreview = document.getElementById('currencyPreview');
    
    function updateCurrencyPreview() {
        const symbol = currencySymbolInput.value || 'NOK';
        const position = currencyPositionSelect.value;
        const decimalSep = decimalSeparatorSelect.value;
        const thousandsSep = thousandsSeparatorSelect.value;
        
        // Formater eksempelbeløp
        let formattedAmount = '1234.56'.replace('.', decimalSep);
        
        // Legg til tusentallsseparator
        if (thousandsSep !== '') {
            const parts = formattedAmount.split(decimalSep);
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
            formattedAmount = parts.join(decimalSep);
        }
        
        // Plasser valutasymbolet
        if (position === 'before') {
            currencyPreview.textContent = symbol + ' ' + formattedAmount;
        } else {
            currencyPreview.textContent = formattedAmount + ' ' + symbol;
        }
    }
    
    // Oppdater dato- og tidsforhåndsvisning når innstillinger endres
    const dateFormatSelect = document.getElementById('date_format');
    const timeFormatSelect = document.getElementById('time_format');
    const dateTimePreview = document.getElementById('dateTimePreview');
    
    function updateDateTimePreview() {
        // Dette er en forenklet versjon - i en fullstendig implementering 
        // ville vi gjort en AJAX-forespørsel for å få riktig formatert dato/tid
        
        const now = new Date();
        let dateStr = '';
        
        switch (dateFormatSelect.value) {
            case 'd.m.Y':
                dateStr = now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
                break;
            case 'd/m/Y':
                dateStr = now.getDate() + '/' + (now.getMonth() + 1) + '/' + now.getFullYear();
                break;
            case 'm/d/Y':
                dateStr = (now.getMonth() + 1) + '/' + now.getDate() + '/' + now.getFullYear();
                break;
            case 'Y-m-d':
                dateStr = now.getFullYear() + '-' + (now.getMonth() + 1) + '-' + now.getDate();
                break;
            default:
                dateStr = now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
        }
        
        let timeStr = '';
        if (timeFormatSelect.value === 'H:i') {
            timeStr = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
        } else {
            const hours = now.getHours() % 12 || 12;
            timeStr = hours + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + ' ' + (now.getHours() >= 12 ? 'PM' : 'AM');
        }
        
        dateTimePreview.textContent = dateStr + ' ' + timeStr;
    }
    
    // Legg til event listeners
    currencySymbolInput.addEventListener('input', updateCurrencyPreview);
    currencyPositionSelect.addEventListener('change', updateCurrencyPreview);
    decimalSeparatorSelect.addEventListener('change', updateCurrencyPreview);
    thousandsSeparatorSelect.addEventListener('change', updateCurrencyPreview);
    
    dateFormatSelect.addEventListener('change', updateDateTimePreview);
    timeFormatSelect.addEventListener('change', updateDateTimePreview);
    
    // Initialiser forhåndsvisningene
    updateCurrencyPreview();
    updateDateTimePreview();
});
</script>

<?php
// Inkluder footer
include '../../includes/footer.php';
?>