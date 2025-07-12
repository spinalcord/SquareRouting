<?php

declare(strict_types=1);

namespace SquareRouting\Tests\Core;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SquareRouting\Core\Configuration;
use SquareRouting\Core\Database;
use SquareRouting\Core\Schema\ColumnName;
use SquareRouting\Core\Schema\TableName;
use InvalidArgumentException;
use Exception;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private MockObject|Database $databaseMock;

    protected function setUp(): void
    {
        $this->databaseMock = $this->createMock(Database::class);
        
        // Mock für select() beim Laden der Konfigurationen
        $this->databaseMock
            ->method('select')
            ->with(TableName::CONFIGURATION)
            ->willReturn([]);
            
        $this->configuration = new Configuration($this->databaseMock, false);
    }

    // =================================================================
    // CONSTRUCTOR TESTS
    // =================================================================

    public function testConstructorWithAutoSaveEnabled(): void
    {
        $this->databaseMock
            ->method('select')
            ->willReturn([]);

        $config = new Configuration($this->databaseMock, true);
        
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testConstructorLoadsExistingConfigurations(): void
    {
        $existingConfigs = [
            [
                ColumnName::NAME => 'app.name',
                ColumnName::VALUE => 'Test App',
                ColumnName::DEFAULT_VALUE => 'Default App',
                ColumnName::LABEL => 'Application Name',
                ColumnName::DESCRIPTION => 'The name of the application',
                ColumnName::TYPE => 'string'
            ],
            [
                ColumnName::NAME => 'app.debug',
                ColumnName::VALUE => '1',
                ColumnName::DEFAULT_VALUE => '0',
                ColumnName::LABEL => 'Debug Mode',
                ColumnName::DESCRIPTION => 'Enable debug mode',
                ColumnName::TYPE => 'boolean'
            ]
        ];

        $databaseMock = $this->createMock(Database::class);
        $databaseMock
            ->method('select')
            ->willReturn($existingConfigs);

        $config = new Configuration($databaseMock);

        $this->assertEquals('Test App', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertTrue($config->isRegistered('app.name'));
        $this->assertTrue($config->isRegistered('app.debug'));
    }

    public function testConstructorHandlesDatabaseException(): void
    {
        $databaseMock = $this->createMock(Database::class);
        $databaseMock
            ->method('select')
            ->willThrowException(new Exception('Table does not exist'));

        // Sollte keine Exception werfen, sondern mit leerer Konfiguration starten
        $config = new Configuration($databaseMock);
        
        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertEquals([], $config->all());
    }

    // =================================================================
    // REGISTER TESTS
    // =================================================================

    public function testRegisterNewConfiguration(): void
    {
        $key = 'app.name';
        $defaultValue = 'My App';
        $label = 'Application Name';
        $description = 'The name of the application';

        $this->databaseMock
            ->expects($this->once())
            ->method('exists')
            ->with(TableName::CONFIGURATION, [ColumnName::NAME => $key])
            ->willReturn(false);

        $this->databaseMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                TableName::CONFIGURATION,
                $this->callback(function ($data) use ($key, $defaultValue, $label, $description) {
                    return $data[ColumnName::NAME] === $key &&
                           $data[ColumnName::VALUE] === $defaultValue &&
                           $data[ColumnName::DEFAULT_VALUE] === $defaultValue &&
                           $data[ColumnName::LABEL] === $label &&
                           $data[ColumnName::DESCRIPTION] === $description &&
                           $data[ColumnName::TYPE] === 'string';
                })
            )
            ->willReturn('1');

        $result = $this->configuration->register($key, $defaultValue, $label, $description);

        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals($defaultValue, $this->configuration->get($key));
        $this->assertTrue($this->configuration->isRegistered($key));
    }

    public function testRegisterExistingConfigurationUpdatesInfo(): void
    {
        $key = 'app.name';
        $defaultValue = 'Updated App';

        $this->databaseMock
            ->method('exists')
            ->willReturn(true);

        $this->databaseMock
            ->expects($this->once())
            ->method('update')
            ->with(
                TableName::CONFIGURATION,
                $this->callback(function ($data) use ($defaultValue) {
                    return $data[ColumnName::DEFAULT_VALUE] === $defaultValue;
                }),
                [ColumnName::NAME => $key]
            )
            ->willReturn(1);

        $this->configuration->register($key, $defaultValue);

        $this->assertTrue($this->configuration->isRegistered($key));
    }

    public function testRegisterWithAutoSave(): void
    {
        $config = new Configuration($this->databaseMock, true);

        $this->databaseMock
            ->method('exists')
            ->willReturn(false);

        $this->databaseMock
            ->method('insert')
            ->willReturn('1');

        // save() sollte automatisch aufgerufen werden
        $this->databaseMock
            ->expects($this->once())
            ->method('update');

        $config->register('test.key', 'test value');
    }

    public function testRegisterWithComplexDataTypes(): void
    {
        $arrayValue = ['key1' => 'value1', 'key2' => 'value2'];
        $objectValue = new \stdClass();
        $objectValue->prop = 'value';

        $this->databaseMock
            ->method('exists')
            ->willReturn(false);

        $this->databaseMock
            ->method('insert')
            ->willReturn('1');

        $this->configuration->register('test.array', $arrayValue);
        $this->configuration->register('test.object', $objectValue);

        $this->assertEquals($arrayValue, $this->configuration->get('test.array'));
        $this->assertEquals($objectValue, $this->configuration->get('test.object'));
    }

    // =================================================================
    // KEY VALIDATION TESTS
    // =================================================================

    public function testRegisterWithInvalidKeyThrowsException(): void
    {
        $invalidKeys = [
            '',           // Empty
            '   ',        // Whitespace only
            'key with spaces',  // Spaces
            'key@symbol', // Invalid character
            'key..double', // Consecutive dots
            '.startdot',  // Starts with dot
            'enddot.',    // Ends with dot
        ];

        foreach ($invalidKeys as $invalidKey) {
            $this->expectException(InvalidArgumentException::class);
            $this->configuration->register($invalidKey, 'value');
        }
    }

    public function testRegisterWithNamespaceConflictThrowsException(): void
    {
        // Erst einen direkten Wert registrieren
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('app', 'direct value');

        // Dann versuchen, einen Namespace zu erstellen
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('would use \'app\' as a namespace');

        $this->configuration->register('app.name', 'app name');
    }

    public function testRegisterDirectValueConflictWithExistingNamespace(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        // Erst einen Namespace erstellen
        $this->configuration->register('app.name', 'app name');

        // Dann versuchen, einen direkten Wert zu registrieren
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already used as a namespace');

        $this->configuration->register('app', 'direct value');
    }

    // =================================================================
    // GET/SET TESTS
    // =================================================================

    public function testGetExistingConfiguration(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'test value');

        $this->assertEquals('test value', $this->configuration->get('test.key'));
    }

    public function testGetNonExistentConfigurationReturnsDefault(): void
    {
        $this->assertEquals('default', $this->configuration->get('nonexistent', 'default'));
        $this->assertNull($this->configuration->get('nonexistent'));
    }

    public function testGetRegisteredDefaultValue(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default value');

        $this->assertEquals('default value', $this->configuration->get('test.key'));
    }

    public function testSetRegisteredConfiguration(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default');

        $result = $this->configuration->set('test.key', 'new value');

        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('new value', $this->configuration->get('test.key'));
    }

    public function testSetUnregisteredConfigurationThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered');

        $this->configuration->set('unregistered.key', 'value');
    }

    public function testSetWithAutoSave(): void
    {
        $config = new Configuration($this->databaseMock, true);

        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        // save() sollte automatisch aufgerufen werden - einmal beim register() und einmal beim set()
        $this->databaseMock
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturn(1);

        $config->register('test.key', 'default');
        $config->set('test.key', 'new value');
    }

    // =================================================================
    // ARRAY TESTS
    // =================================================================

    public function testGetArrayWithNamespace(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('app.name', 'Test App');
        $this->configuration->register('app.version', '1.0.0');
        $this->configuration->register('app.database.host', 'localhost');
        $this->configuration->register('app.database.port', 3306);
        $this->configuration->register('other.setting', 'value');

        $appConfig = $this->configuration->getArray('app');

        $expected = [
            'name' => 'Test App',
            'version' => '1.0.0',
            'database' => [
                'host' => 'localhost',
                'port' => 3306
            ]
        ];

        $this->assertEquals($expected, $appConfig);
    }

    public function testGetArrayWithoutNamespace(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('app.name', 'Test App');
        $this->configuration->register('database.host', 'localhost');

        $allConfig = $this->configuration->getArray();

        $expected = [
            'app' => [
                'name' => 'Test App'
            ],
            'database' => [
                'host' => 'localhost'
            ]
        ];

        $this->assertEquals($expected, $allConfig);
    }

    public function testGetArrayCaching(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('app.name', 'Test App');

        // Erste Abfrage sollte Cache füllen
        $result1 = $this->configuration->getArray('app');
        
        // Zweite Abfrage sollte aus Cache kommen
        $result2 = $this->configuration->getArray('app');

        $this->assertEquals($result1, $result2);
    }

    public function testGetArrayWithCacheDisabled(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->setCacheEnabled(false);
        $this->configuration->register('app.name', 'Test App');

        $result = $this->configuration->getArray('app');
        
        $this->assertEquals(['name' => 'Test App'], $result);
    }

    // =================================================================
    // UTILITY TESTS
    // =================================================================

    public function testHasConfiguration(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('existing.key', 'value');

        $this->assertTrue($this->configuration->has('existing.key'));
        $this->assertFalse($this->configuration->has('nonexistent.key'));
    }

    public function testIsRegistered(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('registered.key', 'value');

        $this->assertTrue($this->configuration->isRegistered('registered.key'));
        $this->assertFalse($this->configuration->isRegistered('unregistered.key'));
    }

    public function testGetRegisteredKeys(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('key1', 'value1');
        $this->configuration->register('key2', 'value2');

        $keys = $this->configuration->getRegisteredKeys();

        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertCount(2, $keys);
    }

    public function testGetRegistrationInfo(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default', 'Test Label', 'Test Description');

        $info = $this->configuration->getRegistrationInfo('test.key');

        $this->assertEquals([
            'defaultValue' => 'default',
            'label' => 'Test Label',
            'description' => 'Test Description',
            'type' => 'string'
        ], $info);

        $this->assertNull($this->configuration->getRegistrationInfo('nonexistent'));
    }

    // =================================================================
    // SAVE/RESET TESTS
    // =================================================================

    public function testSaveConfigurations(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default');
        $this->configuration->set('test.key', 'new value');

        $this->databaseMock
            ->expects($this->once())
            ->method('update')
            ->with(
                TableName::CONFIGURATION,
                $this->callback(function ($data) {
                    return $data[ColumnName::VALUE] === 'new value';
                }),
                [ColumnName::NAME => 'test.key']
            )
            ->willReturn(1);

        $result = $this->configuration->save();

        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testSaveWhenNotDirty(): void
    {
        // Wenn nichts geändert wurde, sollte kein Update stattfinden
        $this->databaseMock
            ->expects($this->never())
            ->method('update');

        $this->configuration->save();
    }

    public function testResetConfiguration(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default value');
        $this->configuration->set('test.key', 'changed value');

        $result = $this->configuration->reset('test.key');

        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('default value', $this->configuration->get('test.key'));
    }

    public function testResetUnregisteredConfigurationThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered');

        $this->configuration->reset('unregistered.key');
    }

    public function testResetAll(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('key1', 'default1');
        $this->configuration->register('key2', 'default2');
        
        $this->configuration->set('key1', 'changed1');
        $this->configuration->set('key2', 'changed2');

        $result = $this->configuration->resetAll();

        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertEquals('default1', $this->configuration->get('key1'));
        $this->assertEquals('default2', $this->configuration->get('key2'));
    }

    public function testResetWithAutoSave(): void
    {
        $config = new Configuration($this->databaseMock, true);

        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $config->register('test.key', 'default');
        $config->set('test.key', 'changed');

        // save() sollte automatisch aufgerufen werden
        $this->databaseMock
            ->expects($this->once())
            ->method('update');

        $config->reset('test.key');
    }

    // =================================================================
    // REMOVE TESTS
    // =================================================================

    public function testRemoveConfiguration(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'value');

        $this->databaseMock
            ->expects($this->once())
            ->method('delete')
            ->with(TableName::CONFIGURATION, [ColumnName::NAME => 'test.key'])
            ->willReturn(1);

        $result = $this->configuration->remove('test.key');

        $this->assertInstanceOf(Configuration::class, $result);
        $this->assertFalse($this->configuration->has('test.key'));
        $this->assertFalse($this->configuration->isRegistered('test.key'));
    }

    // =================================================================
    // BULK OPERATIONS TESTS
    // =================================================================

    public function testAll(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('key1', 'value1');
        $this->configuration->register('key2', 'value2');

        $all = $this->configuration->all();

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2'
        ], $all);
    }

    public function testGetAllWithInfo(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('test.key', 'default', 'Test Label', 'Test Description');
        $this->configuration->set('test.key', 'new value');

        $allWithInfo = $this->configuration->getAllWithInfo();

        $expected = [
            'test.key' => [
                'value' => 'new value',
                'defaultValue' => 'default',
                'label' => 'Test Label',
                'description' => 'Test Description',
                'type' => 'string'
            ]
        ];

        $this->assertEquals($expected, $allWithInfo);
    }

    // =================================================================
    // CACHE TESTS
    // =================================================================

    public function testClearCache(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('app.name', 'Test App');
        
        // Cache füllen
        $this->configuration->getArray('app');
        
        $result = $this->configuration->clearCache();

        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testSetCacheEnabled(): void
    {
        $result = $this->configuration->setCacheEnabled(false);

        $this->assertInstanceOf(Configuration::class, $result);
    }

    // =================================================================
    // DATA TYPE TESTS
    // =================================================================

    public function testDataTypeSerialization(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $testValues = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
            'array' => ['a', 'b', 'c'],
            'object' => (object)['prop' => 'value']
        ];

        foreach ($testValues as $key => $value) {
            $this->configuration->register("test.{$key}", $value);
            $this->assertEquals($value, $this->configuration->get("test.{$key}"));
        }
    }

    // =================================================================
    // EDGE CASES
    // =================================================================

    public function testValidKeyFormats(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $validKeys = [
            'simple',
            'with_underscore',
            'with-hyphen',
            'with.dot',
            'nested.key.deep',
            'mix_of-all.formats',
            'key123',
            'a',
            '123'
        ];

        foreach ($validKeys as $key) {
            $this->configuration->register($key, 'value');
            $this->assertTrue($this->configuration->isRegistered($key));
        }
    }

    public function testGetArrayWithDirectMatch(): void
    {
        $this->databaseMock->method('exists')->willReturn(false);
        $this->databaseMock->method('insert')->willReturn('1');

        $this->configuration->register('directkey', 'direct value');

        $result = $this->configuration->getArray('directkey');

        $this->assertEquals(['directkey' => 'direct value'], $result);
    }
}