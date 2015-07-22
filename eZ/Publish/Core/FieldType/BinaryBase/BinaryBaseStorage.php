<?php
/**
 * File containing the BinaryBaseStorage class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version 2014.11.1
 */

namespace eZ\Publish\Core\FieldType\BinaryBase;

use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\FieldType\GatewayBasedStorage;
use eZ\Publish\Core\IO\IOServiceInterface;
use eZ\Publish\SPI\FieldType\BinaryBase\PathGenerator;
use eZ\Publish\SPI\Persistence\Content\VersionInfo;
use eZ\Publish\SPI\Persistence\Content\Field;
use eZ\Publish\SPI\IO\MimeTypeDetector;
use Psr\Log\LoggerInterface;

/**
 * Storage for binary files
 */
class BinaryBaseStorage extends GatewayBasedStorage
{
    /**
     * An instance of IOService configured to store to the images folder
     *
     * @var IOServiceInterface
     */
    protected $IOService;

    /** @var PathGenerator */
    protected $pathGenerator;

    /**
     * @var MimeTypeDetector
     */
    protected $mimeTypeDetector;

    /**
     * Construct from gateways
     *
     * @param \eZ\Publish\Core\FieldType\StorageGateway[] $gateways
     * @param IOServiceInterface $IOService
     * @param PathGenerator $pathGenerator
     * @param MimeTypeDetector $mimeTypeDetector
     */
    public function __construct( array $gateways, IOServiceInterface $IOService, PathGenerator $pathGenerator, MimeTypeDetector $mimeTypeDetector )
    {
        parent::__construct( $gateways );
        $this->IOService = $IOService;
        $this->pathGenerator = $pathGenerator;
        $this->mimeTypeDetector = $mimeTypeDetector;
    }

    /**
     * Allows custom field types to store data in an external source (e.g. another DB table).
     *
     * Stores value for $field in an external data source.
     * The whole {@link eZ\Publish\SPI\Persistence\Content\Field} ValueObject is passed and its value
     * is accessible through the {@link eZ\Publish\SPI\Persistence\Content\FieldValue} 'value' property.
     * This value holds the data filled by the user as a {@link eZ\Publish\Core\FieldType\Value} based object,
     * according to the field type (e.g. for TextLine, it will be a {@link eZ\Publish\Core\FieldType\TextLine\Value} object).
     *
     * $field->id = unique ID from the attribute tables (needs to be generated by
     * database back end on create, before the external data source may be
     * called from storing).
     *
     * The context array provides some context for the field handler about the
     * currently used storage engine.
     * The array should at least define 2 keys :
     *   - identifier (connection identifier)
     *   - connection (the connection handler)
     * For example, using Legacy storage engine, $context will be:
     *   - identifier = 'LegacyStorage'
     *   - connection = {@link \eZ\Publish\Core\Persistence\Database\DatabaseHandler} object handler (for DB connection),
     *                  to be used accordingly to
     *                  {@link http://incubator.apache.org/zetacomponents/documentation/trunk/Database/tutorial.html ezcDatabase} usage
     *
     * @param \eZ\Publish\SPI\Persistence\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     * @param array $context
     *
     * @return void
     */
    public function storeFieldData( VersionInfo $versionInfo, Field $field, array $context )
    {
        if ( $field->value->externalData === null )
        {
            // Nothing to store
            return false;
        }

        // no mimeType means we are dealing with an input, local file
        if ( !isset( $field->value->externalData['mimeType'] ) )
        {
            $field->value->externalData['mimeType'] =
                $this->mimeTypeDetector->getFromPath( $field->value->externalData['inputUri'] );
        }

        $storedValue = $field->value->externalData;

        // The file referenced in externalData MAY be an existing IOService file which we can use
        if ( $storedValue['id'] === null )
        {
            $createStruct = $this->IOService->newBinaryCreateStructFromLocalFile(
                $storedValue['inputUri']
            );
            $storagePath = $this->pathGenerator->getStoragePathForField( $field, $versionInfo );
            $createStruct->id = $storagePath;
            $binaryFile = $this->IOService->createBinaryFile( $createStruct );
            $storedValue['id'] = $binaryFile->id;
            $storedValue['mimeType'] = $createStruct->mimeType;
            $storedValue['uri'] = $binaryFile->uri;
        }

        $field->value->externalData = $storedValue;

        $this->removeOldFile( $field->id, $versionInfo->versionNo, $context );

        $this->getGateway( $context )->storeFileReference( $versionInfo, $field );
    }

    public function copyLegacyField( VersionInfo $versionInfo, Field $field, Field $originalField, array $context )
    {
        if ( $originalField->value->externalData === null )
            return false;

        // field translations have their own file reference, but to the original file
        $originalField->value->externalData['id'];

        return $this->getGateway( $context )->storeFileReference( $versionInfo, $field );
    }

    /**
     * Removes the old file referenced by $fieldId in $versionNo, if not
     * referenced else where
     *
     * @param mixed $fieldId
     * @param string $versionNo
     * @param array $context
     *
     * @return void
     */
    protected function removeOldFile( $fieldId, $versionNo, array $context )
    {
        $gateway = $this->getGateway( $context );

        $fileReference = $gateway->getFileReferenceData( $fieldId, $versionNo );
        if ( $fileReference === null )
        {
            // No previous file
            return;
        }

        $gateway->removeFileReference( $fieldId, $versionNo );

        $fileCounts = $gateway->countFileReferences( array( $fileReference['id'] ) );

        if ( $fileCounts[$fileReference['id']] === 0 )
        {
            $binaryFile = $this->IOService->loadBinaryFile( $fileReference['id'] );
            $this->IOService->deleteBinaryFile( $binaryFile );
        }
    }

    /**
     * Populates $field value property based on the external data.
     * $field->value is a {@link eZ\Publish\SPI\Persistence\Content\FieldValue} object.
     * This value holds the data as a {@link eZ\Publish\Core\FieldType\Value} based object,
     * according to the field type (e.g. for TextLine, it will be a {@link eZ\Publish\Core\FieldType\TextLine\Value} object).
     *
     * @param \eZ\Publish\SPI\Persistence\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     * @param array $context
     *
     * @return void
     */
    public function getFieldData( VersionInfo $versionInfo, Field $field, array $context )
    {
        $field->value->externalData = $this->getGateway( $context )->getFileReferenceData( $field->id, $versionInfo->versionNo );
        if ( $field->value->externalData !== null )
        {
            $binaryFile = $this->IOService->loadBinaryFile( $field->value->externalData['id'] );
            $field->value->externalData['fileSize'] = $binaryFile->size;
            $field->value->externalData['uri'] = $binaryFile->uri;
        }
    }

    /**
     * Deletes all referenced external data
     *
     * @param VersionInfo $versionInfo
     * @param array $fieldIds
     * @param array $context
     *
     * @return boolean
     */
    public function deleteFieldData( VersionInfo $versionInfo, array $fieldIds, array $context )
    {
        if ( empty( $fieldIds ) )
        {
            return;
        }

        $gateway = $this->getGateway( $context );

        $referencedFiles = $gateway->getReferencedFiles( $fieldIds, $versionInfo->versionNo );

        $gateway->removeFileReferences( $fieldIds, $versionInfo->versionNo );

        $referenceCountMap = $gateway->countFileReferences( $referencedFiles );

        foreach ( $referenceCountMap as $filePath => $count )
        {
            if ( $count === 0 )
            {
                $binaryFile = $this->IOService->loadBinaryFile( $filePath );
                $this->IOService->deleteBinaryFile( $binaryFile );
            }
        }
    }

    /**
     * Checks if field type has external data to deal with
     *
     * @return boolean
     */
    public function hasFieldData()
    {
        return true;
    }

    /**
     * @param \eZ\Publish\SPI\Persistence\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     * @param array $context
     *
     * @return void
     */
    public function getIndexData( VersionInfo $versionInfo, Field $field, array $context )
    {
    }
}