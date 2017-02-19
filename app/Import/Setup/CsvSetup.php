<?php
/**
 * CsvSetup.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Import\Setup;


use ExpandedForm;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Import\Mapper\MapperInterface;
use FireflyIII\Import\MapperPreProcess\PreProcessorInterface;
use FireflyIII\Import\Specifics\SpecificInterface;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use Illuminate\Http\Request;
use League\Csv\Reader;
use Log;
use Symfony\Component\HttpFoundation\FileBag;

/**
 * Class CsvSetup
 *
 * @package FireflyIII\Import\Importer
 */
class CsvSetup implements SetupInterface
{
    /** @var  Account */
    public $defaultImportAccount;
    /** @var  ImportJob */
    public $job;

    /**
     * CsvImporter constructor.
     */
    public function __construct()
    {
        $this->defaultImportAccount = new Account;
    }

    /**
     * Create initial (empty) configuration array.
     *
     * @return bool
     */
    public function configure(): bool
    {
        if (is_null($this->job->configuration) || (is_array($this->job->configuration) && count($this->job->configuration) === 0)) {
            Log::debug('No config detected, will create empty one.');
            $this->job->configuration = config('csv.default_config');
            $this->job->save();

            return true;
        }

        // need to do nothing, for now.
        Log::debug('Detected config in upload, will use that one. ', $this->job->configuration);

        return true;
    }

    /**
     * @return array
     */
    public function getConfigurationData(): array
    {
        /** @var AccountRepositoryInterface $accountRepository */
        $accountRepository = app(AccountRepositoryInterface::class);
        $accounts          = $accountRepository->getAccountsByType([AccountType::DEFAULT, AccountType::ASSET]);
        $delimiters        = [
            ','   => trans('form.csv_comma'),
            ';'   => trans('form.csv_semicolon'),
            'tab' => trans('form.csv_tab'),
        ];

        $specifics = [];

        // collect specifics.
        foreach (config('csv.import_specifics') as $name => $className) {
            $specifics[$name] = [
                'name'        => $className::getName(),
                'description' => $className::getDescription(),
            ];
        }

        $data = [
            'accounts'           => ExpandedForm::makeSelectList($accounts),
            'specifix'           => [],
            'delimiters'         => $delimiters,
            'upload_path'        => storage_path('upload'),
            'is_upload_possible' => is_writable(storage_path('upload')),
            'specifics'          => $specifics,
        ];

        return $data;
    }

    /**
     * This method returns the data required for the view that will let the user add settings to the import job.
     *
     * @return array
     */
    public function getDataForSettings(): array
    {
        Log::debug('Now in getDataForSettings()');
        if ($this->doColumnRoles()) {
            Log::debug('doColumnRoles() is true.');
            $data = $this->getDataForColumnRoles();

            return $data;
        }

        if ($this->doColumnMapping()) {
            Log::debug('doColumnMapping() is true.');
            $data = $this->getDataForColumnMapping();

            return $data;
        }

        echo 'no settings to do.';
        exit;

    }

    /**
     * This method returns the name of the view that will be shown to the user to further configure
     * the import job.
     *
     * @return string
     * @throws FireflyException
     */
    public function getViewForSettings(): string
    {
        if ($this->doColumnRoles()) {
            return 'import.csv.roles';
        }

        if ($this->doColumnMapping()) {
            return 'import.csv.map';
        }
        throw new FireflyException('There is no view for the current CSV import step.');
    }

    /**
     * This method returns whether or not the user must configure this import
     * job further.
     *
     * @return bool
     */
    public function requireUserSettings(): bool
    {
        Log::debug(sprintf('doColumnMapping is %s', $this->doColumnMapping()));
        Log::debug(sprintf('doColumnRoles is %s', $this->doColumnRoles()));
        if ($this->doColumnMapping() || $this->doColumnRoles()) {
            Log::debug('Return true');

            return true;
        }
        Log::debug('Return false');

        return false;
    }

    /**
     * @param array   $data
     *
     * @param FileBag $files
     *
     * @return bool
     */
    public function saveImportConfiguration(array $data, FileBag $files): bool
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);
        $importId   = $data['csv_import_account'] ?? 0;
        $account    = $repository->find(intval($importId));

        $hasHeaders            = isset($data['has_headers']) && intval($data['has_headers']) === 1 ? true : false;
        $config                = $this->job->configuration;
        $config['has-headers'] = $hasHeaders;
        $config['date-format'] = $data['date_format'];
        $config['delimiter']   = $data['csv_delimiter'];
        $config['delimiter']   = $config['delimiter'] === 'tab' ? "\t" : $config['delimiter'];

        Log::debug('Entered import account.', ['id' => $importId]);


        if (!is_null($account->id)) {
            Log::debug('Found account.', ['id' => $account->id, 'name' => $account->name]);
            $config['import-account'] = $account->id;
        } else {
            Log::error('Could not find anything for csv_import_account.', ['id' => $importId]);
        }
        // loop specifics.
        if (isset($data['specifics']) && is_array($data['specifics'])) {
            foreach ($data['specifics'] as $name => $enabled) {
                // verify their content.
                $className = sprintf('FireflyIII\Import\Specifics\%s', $name);
                if (class_exists($className)) {
                    $config['specifics'][$name] = 1;
                }
            }
        }
        $this->job->configuration = $config;
        $this->job->save();

        return true;


    }

    /**
     * @param ImportJob $job
     */
    public function setJob(ImportJob $job)
    {
        $this->job = $job;
    }

    /**
     * Store the settings filled in by the user, if applicable.
     *
     * @param Request $request
     *
     */
    public function storeSettings(Request $request)
    {
        $config = $this->job->configuration;
        $all    = $request->all();
        if ($request->get('settings') == 'roles') {
            $count = $config['column-count'];

            $roleSet = 0; // how many roles have been defined
            $mapSet  = 0;  // how many columns must be mapped
            for ($i = 0; $i < $count; $i++) {
                $selectedRole = $all['role'][$i] ?? '_ignore';
                $doMapping    = isset($all['map'][$i]) && $all['map'][$i] == '1' ? true : false;
                if ($selectedRole == '_ignore' && $doMapping === true) {
                    $doMapping = false; // cannot map ignored columns.
                }
                if ($selectedRole != '_ignore') {
                    $roleSet++;
                }
                if ($doMapping === true) {
                    $mapSet++;
                }
                $config['column-roles'][$i]      = $selectedRole;
                $config['column-do-mapping'][$i] = $doMapping;
            }
            if ($roleSet > 0) {
                $config['column-roles-complete'] = true;
                $this->job->configuration        = $config;
                $this->job->save();
            }
            if ($mapSet === 0) {
                // skip setting of map:
                $config['column-mapping-complete'] = true;
            }
        }
        if ($request->get('settings') == 'map') {
            if (isset($all['mapping'])) {
                foreach ($all['mapping'] as $index => $data) {
                    $config['column-mapping-config'][$index] = [];
                    foreach ($data as $value => $mapId) {
                        $mapId = intval($mapId);
                        if ($mapId !== 0) {
                            $config['column-mapping-config'][$index][$value] = intval($mapId);
                        }
                    }
                }
            }

            // set thing to be completed.
            $config['column-mapping-complete'] = true;
            $this->job->configuration          = $config;
            $this->job->save();
        }
    }

    /**
     * @return bool
     */
    private function doColumnMapping(): bool
    {
        $mapArray = $this->job->configuration['column-do-mapping'] ?? [];
        $doMap    = false;
        foreach ($mapArray as $value) {
            if ($value === true) {
                $doMap = true;
                break;
            }
        }

        return $this->job->configuration['column-mapping-complete'] === false && $doMap;
    }

    /**
     * @return bool
     */
    private function doColumnRoles(): bool
    {
        return $this->job->configuration['column-roles-complete'] === false;
    }

    /**
     * @return array
     * @throws FireflyException
     */
    private function getDataForColumnMapping(): array
    {
        $config  = $this->job->configuration;
        $data    = [];
        $indexes = [];

        foreach ($config['column-do-mapping'] as $index => $mustBeMapped) {
            if ($mustBeMapped) {

                $column = $config['column-roles'][$index] ?? '_ignore';

                // is valid column?
                $validColumns = array_keys(config('csv.import_roles'));
                if (!in_array($column, $validColumns)) {
                    throw new FireflyException(sprintf('"%s" is not a valid column.', $column));
                }

                $canBeMapped   = config('csv.import_roles.' . $column . '.mappable');
                $preProcessMap = config('csv.import_roles.' . $column . '.pre-process-map');
                if ($canBeMapped) {
                    $mapperClass = config('csv.import_roles.' . $column . '.mapper');
                    $mapperName  = sprintf('\\FireflyIII\\Import\Mapper\\%s', $mapperClass);
                    /** @var MapperInterface $mapper */
                    $mapper       = new $mapperName;
                    $indexes[]    = $index;
                    $data[$index] = [
                        'name'          => $column,
                        'mapper'        => $mapperName,
                        'index'         => $index,
                        'options'       => $mapper->getMap(),
                        'preProcessMap' => null,
                        'values'        => [],
                    ];
                    if ($preProcessMap) {
                        $preClass                      = sprintf(
                            '\\FireflyIII\\Import\\MapperPreProcess\\%s',
                            config('csv.import_roles.' . $column . '.pre-process-mapper')
                        );
                        $data[$index]['preProcessMap'] = $preClass;
                    }
                }

            }
        }

        // in order to actually map we also need all possible values from the CSV file.
        $content = $this->job->uploadFileContents();
        /** @var Reader $reader */
        $reader = Reader::createFromString($content);
        $reader->setDelimiter($config['delimiter']);
        $results        = $reader->fetch();
        $validSpecifics = array_keys(config('csv.import_specifics'));

        foreach ($results as $rowIndex => $row) {

            // skip first row?
            if ($rowIndex === 0 && $config['has-headers']) {
                continue;
            }

            // run specifics here:
            // and this is the point where the specifix go to work.
            foreach ($config['specifics'] as $name => $enabled) {

                if (!in_array($name, $validSpecifics)) {
                    throw new FireflyException(sprintf('"%s" is not a valid class name', $name));
                }
                $class = config('csv.import_specifics.' . $name);
                /** @var SpecificInterface $specific */
                $specific = app($class);

                // it returns the row, possibly modified:
                $row = $specific->run($row);
            }

            //do something here
            foreach ($indexes as $index) { // this is simply 1, 2, 3, etc.
                if (!isset($row[$index])) {
                    // don't really know how to handle this. Just skip, for now.
                    continue;
                }
                $value = $row[$index];
                if (strlen($value) > 0) {

                    // we can do some preprocessing here,
                    // which is exclusively to fix the tags:
                    if (!is_null($data[$index]['preProcessMap'])) {
                        /** @var PreProcessorInterface $preProcessor */
                        $preProcessor           = app($data[$index]['preProcessMap']);
                        $result                 = $preProcessor->run($value);
                        $data[$index]['values'] = array_merge($data[$index]['values'], $result);

                        Log::debug($rowIndex . ':' . $index . 'Value before preprocessor', ['value' => $value]);
                        Log::debug($rowIndex . ':' . $index . 'Value after preprocessor', ['value-new' => $result]);
                        Log::debug($rowIndex . ':' . $index . 'Value after joining', ['value-complete' => $data[$index]['values']]);


                        continue;
                    }

                    $data[$index]['values'][] = $value;
                }
            }
        }
        foreach ($data as $index => $entry) {
            $data[$index]['values'] = array_unique($data[$index]['values']);
        }

        return $data;
    }

    /**
     * This method collects the data that will enable a user to choose column content.
     *
     * @return array
     */
    private function getDataForColumnRoles(): array
    {
        Log::debug('Now in getDataForColumnRoles()');
        $config = $this->job->configuration;
        $data   = [
            'columns'       => [],
            'columnCount'   => 0,
            'columnHeaders' => [],
        ];

        // show user column role configuration.
        $content = $this->job->uploadFileContents();

        // create CSV reader.
        $reader = Reader::createFromString($content);
        $reader->setDelimiter($config['delimiter']);
        $start  = $config['has-headers'] ? 1 : 0;
        $end    = $start + config('csv.example_rows');
        $header = [];
        if ($config['has-headers']) {
            $header = $reader->fetchOne(0);
        }


        // collect example data in $data['columns']
        Log::debug(sprintf('While %s is smaller than %d', $start, $end));
        while ($start < $end) {
            $row = $reader->fetchOne($start);
            Log::debug(sprintf('Row %d has %d columns', $start, count($row)));
            // run specifics here:
            // and this is the point where the specifix go to work.
            foreach ($config['specifics'] as $name => $enabled) {
                /** @var SpecificInterface $specific */
                $specific = app('FireflyIII\Import\Specifics\\' . $name);
                Log::debug(sprintf('Will now apply specific "%s" to row %d.', $name, $start));
                // it returns the row, possibly modified:
                $row = $specific->run($row);
            }

            foreach ($row as $index => $value) {
                $value                         = trim($value);
                $data['columnHeaders'][$index] = $header[$index] ?? '';
                if (strlen($value) > 0) {
                    $data['columns'][$index][] = $value;
                }
            }
            $start++;
            $data['columnCount'] = count($row) > $data['columnCount'] ? count($row) : $data['columnCount'];
        }

        // make unique example data
        foreach ($data['columns'] as $index => $values) {
            $data['columns'][$index] = array_unique($values);
        }

        $data['set_roles'] = [];
        // collect possible column roles:
        $data['available_roles'] = [];
        foreach (array_keys(config('csv.import_roles')) as $role) {
            $data['available_roles'][$role] = trans('csv.column_' . $role);
        }

        $config['column-count']   = $data['columnCount'];
        $this->job->configuration = $config;
        $this->job->save();

        return $data;


    }
}
