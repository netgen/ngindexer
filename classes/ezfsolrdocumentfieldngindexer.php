<?php

class ezfSolrDocumentFieldNgIndexer extends ezfSolrDocumentFieldBase
{
    const NGINDEXER_FIELD_PREFIX = 'ngindexer_';

    public function getData()
    {
        $data = array();
        $iniGroups = eZINI::instance( 'ngindexer.ini' )->groups();

        $contentObjectAttribute = $this->ContentObjectAttribute;
        $classIdentifier = $contentObjectAttribute->object()->contentClassIdentifier();
        $classAttributeIdentifier = $contentObjectAttribute->contentClassAttributeIdentifier();

        foreach ( $iniGroups as $iniGroupName => $iniGroup )
        {
            $iniGroupNameArray = explode( '/', $iniGroupName );
            if ( is_array( $iniGroupNameArray ) && count( $iniGroupNameArray ) == 3 &&
                 $iniGroupNameArray[0] == $classIdentifier && $iniGroupNameArray[1] == $classAttributeIdentifier )
            {
                $ngIndexerFieldName = $iniGroupNameArray[2];
                $indexTarget = isset( $iniGroup['IndexTarget'] ) ? trim( $iniGroup['IndexTarget'] ) : '';
                $classIdentifiers = isset( $iniGroup['ClassIdentifiers'] ) && is_array( $iniGroup['ClassIdentifiers'] ) ? $iniGroup['ClassIdentifiers'] : null;
                $indexType = isset( $iniGroup['IndexType'] ) ? trim( $iniGroup['IndexType'] ) : '';
                $attribute = isset( $iniGroup['Attribute'] ) ? trim( $iniGroup['Attribute'] ) : '';

                $targets = self::fetchIndexTargets( $indexTarget, $classIdentifiers, $contentObjectAttribute );

                if ( !empty( $targets ) )
                {
                    self::appendData( $indexType, $attribute, $targets, $ngIndexerFieldName, $data );
                }
            }
        }

        return $data;
    }

    private static function fetchIndexTargets( $indexTarget, $classIdentifiers, $contentObjectAttribute )
    {
        if ( !is_array( $classIdentifiers ) )
            return array();

        if ( $indexTarget == 'parent_line' )
        {
            $path = $contentObjectAttribute->object()->mainNode()->fetchPath();

            if ( !empty( $classIdentifiers ) )
            {
                $targets = array();

                foreach ( $path as $pathNode )
                    if ( in_array( $pathNode->classIdentifier(), $classIdentifiers ) )
                        $targets[] = $pathNode;

                return $targets;
            }

            return $path;
        }
        else if ( $indexTarget == 'parent' )
        {
            $parentNode = $contentObjectAttribute->object()->mainNode()->fetchParent();

            if ( empty( $classIdentifiers ) || in_array( $parentNode->classIdentifier(), $classIdentifiers ) )
                return array( $parentNode );
        }
        else if ( $indexTarget == 'children' || $indexTarget == 'subtree' )
        {
            $children = eZContentObjectTreeNode::subTreeByNodeID( array(
                            'ClassFilterType' => !empty( $classIdentifiers ) ? 'include' : false,
                            'ClassFilterArray' => !empty( $classIdentifiers ) ? $classIdentifiers : false,
                            'Depth' => $indexTarget == 'children' ? 1 : false
                        ), $contentObjectAttribute->object()->mainNode()->attribute( 'node_id' ) );

            if ( is_array( $children ) )
                return $children;
        }

        return array();
    }

    private static function appendData( $indexType, $attribute, $targets, $ngIndexerFieldName, &$data )
    {
        if ( $indexType != 'object' && empty( $attribute ) )
            return;

        if ( $indexType == 'object' )
        {
            $handlerData = array();
            foreach ( $targets as $target )
            {
                foreach ( $target->dataMap() as $key => $value )
                {
                    if ( $value->attribute( 'data_type_string' ) != 'ngindexer' )
                    {
                        $handler = ezfSolrDocumentFieldBase::getInstance( $value );
                        $handlerData[$key][] = $handler->getData();
                    }
                }
            }

            foreach ( $handlerData as $handlerDataArray )
            {
                foreach ( $handlerDataArray as $dataFields )
                {
                    self::appendDataFields( $dataFields, $ngIndexerFieldName, $data );
                }
            }
        }
        else if ( $indexType == 'object_attribute' )
        {
            foreach ( $targets as $target )
            {
                $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $attribute . '____t';
                $data[$fieldName][] = $target->object()->attribute( $attribute );
            }
        }
        else if ( $indexType == 'node_attribute' )
        {
            foreach ( $targets as $target )
            {
                $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $attribute . '____t';
                $data[$fieldName][] = $target->attribute( $attribute );
            }
        }
        else if ( $indexType == 'data_map_attribute' )
        {
            $handlerData = array();
            foreach ( $targets as $target )
            {
                $dataMap = $target->dataMap();
                if ( isset( $dataMap[$attribute] ) && $dataMap[$attribute]->attribute( 'data_type_string' ) != 'ngindexer' )
                {
                    $handler = ezfSolrDocumentFieldBase::getInstance( $dataMap[$attribute] );
                    $handlerData[] = $handler->getData();
                }
            }

            foreach ( $handlerData as $dataFields )
            {
                self::appendDataFields( $dataFields, $ngIndexerFieldName, $data );
            }
        }
    }

    private static function appendDataFields( $dataFields, $ngIndexerFieldName, &$data )
    {
        foreach ( $dataFields as $dataFieldKey => $dataFieldValue )
        {
            $fieldType = '____t';
            $fieldTypePosition = strrpos( $dataFieldKey, '_' );
            if ( $fieldTypePosition !== false && $fieldTypePosition + 1 != strlen( $dataFieldKey ) )
                $fieldType = '____' . substr( $dataFieldKey, $fieldTypePosition + 1 );

            $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $dataFieldKey . $fieldType;
            if ( !isset( $data[$fieldName] ) )
                $data[$fieldName] = array();

            if ( is_array( $dataFieldValue ) )
                $data[$fieldName] = array_merge( $data[$fieldName], $dataFieldValue );
            else
                $data[$fieldName][] = $dataFieldValue;
        }
    }
}

?>
