<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogExportApi\Setup;

use Magento\DataExporter\Config\ConfigInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class for generating ExportApi DTOs
 */
class Recurring implements InstallSchemaInterface
{
    /**
     * @var File
     */
    private $fileDriver;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var DirectoryList
     */
    private $dirList;

    /**
     * @var array
     */
    private $baseConfigEntities;

    /**
     * @param File $fileDriver
     * @param ConfigInterface $config
     * @param DirectoryList $dirList
     * @param array $baseConfigEntities
     */
    public function __construct(
        File $fileDriver,
        ConfigInterface $config,
        DirectoryList $dirList,
        array $baseConfigEntities
    ) {
        $this->fileDriver = $fileDriver;
        $this->config = $config;
        $this->dirList = $dirList;
        $this->baseConfigEntities = $baseConfigEntities;
    }

    /**
     * @inheritdoc
     * @throws \RuntimeException
     * @throws FileSystemException
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $outputDir = $this->dirList->getPath(DirectoryList::GENERATED_CODE) . '/Magento/CatalogExportApi';
        $baseNamespace = $this->resolveNameSpace($outputDir);
        $parsedEntities = [];
        foreach ($this->baseConfigEntities as $node) {
            $parsedEntities[] = $this->getConfig($node);
        }
        $parsedArray = \array_merge(...$parsedEntities);
        $classesData = $this->prepareDtoClassData($parsedArray, $baseNamespace);
        $this->createDirectory($outputDir);
        try {
            $this->generateFiles($classesData, $baseNamespace, $outputDir);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not generate ExportApi DTO\'s ' . $e);
        }
    }

    /**
     * Resolve namespace
     *
     * @param string $filePath
     * @return string
     */
    private function resolveNameSpace(string $filePath): string
    {
        $filePath = trim($filePath, DIRECTORY_SEPARATOR);
        return str_replace('/', '\\', strstr($filePath, 'Magento'));
    }

    /**
     * Return entities config.
     *
     * @param string $entity
     * @param array $parsedArray
     * @return array
     */
    private function getConfig(string $entity, $parsedArray = []): array
    {
        $parsedEntity = $this->config->get($entity);
        if ($parsedEntity) {
            $parsedArray[$entity] = $parsedEntity;

            foreach ($parsedEntity['field'] as $field) {
                if (!$this->config->isScalar($field['type'])) {
                    $parsedArray = $this->getConfig($field['type'], $parsedArray);
                }
            }
        }
        return $parsedArray;
    }

    /**
     * Build structure required to build DTO
     *
     * @param array $parsedArray
     * @param string $baseNamespace
     * @return array
     */
    private function prepareDtoClassData(array $parsedArray, string $baseNamespace): array
    {
        $result = [];
        if (empty($parsedArray)) {
            return $result;
        }

        foreach ($parsedArray as $schemaConfig) {
            foreach ($schemaConfig['field'] as &$field) {
                $field['type'] = $this->mapType($field['type'], $baseNamespace);
                $field['name'] = lcfirst(str_replace('_', '', ucwords($field['name'], '_')));
            }
            $result[$schemaConfig['name']] = $schemaConfig['field'];
        }

        return $result;
    }

    /**
     * Map type
     *
     * @param string $type
     * @param string $baseNameSpace
     * @return string
     */
    private function mapType(string $type, string $baseNameSpace): string
    {
        switch ($type) {
            case 'Int':
                $type = 'int';
                break;
            case 'ID':
            case 'String':
                $type = 'string';
                break;
            case 'Boolean':
                $type = 'bool';
                break;
            case 'Float':
                $type = 'float';
                break;
            default:
                $type = '\\' . $baseNameSpace . '\\' . $type . '[]|null';
        }

        return $type;
    }

    /**
     * Create directory
     *
     * @param string $outPutLocation
     * @return void
     * @throws FileSystemException
     */
    private function createDirectory(string $outPutLocation): void
    {
        if (!$this->fileDriver->isExists($outPutLocation)) {
            $this->fileDriver->createDirectory($outPutLocation, 0775);
        }
    }

    /**
     * Generate files
     *
     * @param array $generateArray
     * @param string $baseNameSpace
     * @param string $baseFileLocation
     * @return void
     * @throws FileSystemException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function generateFiles(array $generateArray, string $baseNameSpace, string $baseFileLocation): void
    {
        $nonRequiredMethods = ['id'];
        foreach ($generateArray as $className => $phpClassFields) {
            $file = new PhpFile();
            $file->addComment('Copyright © Magento, Inc. All rights reserved.');
            $file->addComment('See COPYING.txt for license details.');
            $file->addComment('');
            $file->addComment('Generated from et_schema.xml. DO NOT EDIT!');
            $file->setStrictTypes();
            $namespace = $file->addNamespace($baseNameSpace);
            $class = $namespace->addClass($className);
            $class->addComment($className . ' entity');
            $class->addComment('');
            $class->addComment('phpcs:disable Magento2.PHP.FinalImplementation');
            $class->addComment('@SuppressWarnings(PHPMD.BooleanGetMethodName)');
            $class->addComment('@SuppressWarnings(PHPMD.TooManyFields)');
            $class->addComment('@SuppressWarnings(PHPMD.ExcessivePublicCount)');
            $class->addComment('@SuppressWarnings(PHPMD.ExcessiveClassComplexity)');
            $class->addComment('@SuppressWarnings(PHPMD.CouplingBetweenObjects)');
            foreach ($phpClassFields as $field) {
                $repeated = $field['repeated'];
                $name = $field['name'];
                $type = $field['type'];

                $commentName = preg_replace('/(?<!\ )[A-Z]/', ' $0', $field['name']);
                $property = $class->addProperty($field['name'])->setPrivate();

                if (true === $repeated) {
                    if (substr($type, -4) !== 'null') {
                        $property->addComment('@var ' . 'array');
                    } else {
                        $property->addComment('@var ' . $type);
                    }
                } else {
                    $property->addComment('@var ' . str_replace('[]|null', '', $type));
                }
                $method = $class->addMethod('get' . ucfirst($name));
                $method->addComment('Get ' . strtolower($commentName));
                $method->addComment('');
                if (true === $repeated) {
                    if (substr($type, -4) !== 'null') {
                        $method->addComment('@return ' . $type . '[]');
                    } else {
                        $method->addComment('@return ' . $type);
                    }
                } else {
                    $method->addComment('@return ' . str_replace('[]|null', '', $type));
                }
                if (true === $repeated) {
                    $method->setReturnType('array');
                } else {
                    $method->setReturnType(str_replace('[]|null', '', $type));
                }
                if (!in_array($name, $nonRequiredMethods)) {
                    $method->setReturnNullable();
                }
                $method->addBody('return $this->' . $name . ';');
                $method = $class->addMethod('set' . ucfirst($name));
                $method->addComment('Set ' . strtolower($commentName));
                $method->addComment('');
                if (true === $repeated) {
                    $method->addComment(
                        '@param ' . str_replace('[]|null', '', $type) . '[] $' . $name
                    );
                } else {
                    $method->addComment(
                        '@param ' . str_replace('[]|null', '', $type) . ' $' . $name
                    );
                }
                $method->addComment('@return void');
                if (true === $repeated) {
                    if (!in_array($name, $nonRequiredMethods)) {
                        $method->addParameter($name, null)->setType('array')->setNullable();
                    } else {
                        $method->addParameter($name, null)->setType('array');
                    }
                } else {
                    if (!in_array($name, $nonRequiredMethods)) {
                        $method->addParameter($name)
                            ->setType(str_replace('[]|null', '', $type))
                            ->setNullable();
                    } else {
                        $method->addParameter($name)->setType(str_replace('[]|null', '', $type));
                    }
                }
                $method->setReturnType('void');
                $method->addBody('$this->' . $name . ' = $' . $name . ';');
            }
            $print = new PsrPrinter();
            $this->writeToFile($baseFileLocation . '/' . $className . '.php', $print->printFile($file));
        }
    }

    /**
     * Write to file
     *
     * @param string $fileLocation
     * @param string $output
     * @return void
     * @throws FileSystemException
     */
    private function writeToFile(string $fileLocation, string $output): void
    {
        $resource = $this->fileDriver->fileOpen($fileLocation, 'w');
        $this->fileDriver->fileWrite($resource, $output);
        $this->fileDriver->fileClose($resource);
    }
}
