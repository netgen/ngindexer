<?php

class ezfSolrDocumentFieldNgIndexer extends ezfSolrDocumentFieldBase
{
    const NGINDEXER_FIELD_PREFIX = 'ngindexer_';

    public function getData()
    {
        $data = array();
        $iniGroups = eZINI::instance( 'ngindexer.ini' )->groups();

        $classIdentifier = $this->ContentObjectAttribute->object()->contentClassIdentifier();
        $classAttributeIdentifier = $this->ContentObjectAttribute->contentClassAttributeIdentifier();

        foreach ( $iniGroups as $iniGroupName => $iniGroup )
        {
            if ( count( explode( '/', $iniGroupName ) ) == 3 && strpos( $iniGroupName, $classIdentifier . '/' . $classAttributeIdentifier . '/' ) === 0 )
            {
                $ngIndexerFieldName = explode( '/', $iniGroupName );
                $ngIndexerFieldName = $ngIndexerFieldName[2];
                $indexTarget = isset( $iniGroup['IndexTarget'] ) ? trim( $iniGroup['IndexTarget'] ) : '';
                $classIdentifiers = isset( $iniGroup['ClassIdentifiers'] ) && is_array( $iniGroup['ClassIdentifiers'] ) ? $iniGroup['ClassIdentifiers'] : null;
                $indexType = isset( $iniGroup['IndexType'] ) ? trim( $iniGroup['IndexType'] ) : '';
                $attribute = isset( $iniGroup['Attribute'] ) ? trim( $iniGroup['Attribute'] ) : '';

                if ( strlen( $indexTarget ) > 0 && strlen( $indexType ) > 0 && strlen( $attribute ) > 0 && $classIdentifiers != null )
                {
                    $targets = array();

                    switch ( $indexTarget )
                    {
                        case 'parent_line':
                        {
                            $path = $this->ContentObjectAttribute->object()->mainNode()->fetchPath();

                            if ( !empty( $classIdentifiers ) )
                            {
                                foreach ( $path as $pathNode )
                                {
                                    if ( in_array( $pathNode->classIdentifier(), $classIdentifiers ) )
                                        $targets[] = $pathNode;
                                }
                            }
                            else
                            {
                                $targets = $path;
                            }
                        } break;
                        case 'parent':
                        {
                            $parentNode = $this->ContentObjectAttribute->object()->mainNode()->fetchParent();

                            if ( empty( $classIdentifiers ) || in_array( $parentNode->classIdentifier(), $classIdentifiers ) )
                                $targets = array( $parentNode );
                        } break;
                        case 'children':
                        case 'subtree':
                        {
                            $children = eZContentObjectTreeNode::subTreeByNodeID( array(
                                            'ClassFilterType' => !empty( $classIdentifiers ) ? 'include' : false,
                                            'ClassFilterArray' => !empty( $classIdentifiers ) ? $classIdentifiers : false,
                                            'Depth' => $indexTarget == 'children' ? 1 : false
                                        ), $this->ContentObjectAttribute->object()->mainNode()->attribute( 'node_id' ) );

                            if ( is_array( $children ) )
                                $targets = $children;
                        } break;
                        default: continue;
                    }

                    if ( !empty( $targets ) )
                    {
                        switch ( $indexType )
                        {
                            case 'object':
                            {
                                $handlerData = array();
                                foreach ( $targets as $index => $target )
                                {
                                    foreach ( $target->dataMap() as $key => $value )
                                    {
                                        if ( $value->dataType()->DataTypeString != 'ngindexer' )
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
                                        foreach ( $dataFields as $dataFieldKey => $dataFieldValue )
                                        {
                                            $fieldType = '____t';
                                            $fieldTypePosition = strrpos( $dataFieldKey, '_' );
                                            if ( $fieldTypePosition !== false && $fieldTypePosition + 1 != strlen( $dataFieldKey ) )
                                                $fieldType = '____' . substr( $dataFieldKey, $fieldTypePosition + 1);

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
                            } break;
                            case 'object_attribute':
                            {
                                foreach ( $targets as $target )
                                {
                                    $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $attribute . '____t';
                                    $data[$fieldName][] = $target->object()->attribute( $attribute );
                                }
                            } break;
                            case 'node_attribute':
                            {
                                foreach ( $targets as $target )
                                {
                                    $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $attribute . '____t';
                                    $data[$fieldName][] = $target->attribute( $attribute );
                                }
                            } break;
                            case 'data_map_attribute':
                            {
                                $handlerData = array();
                                foreach ( $targets as $target )
                                {
                                    $dataMap = $target->dataMap();
                                    if ( isset( $dataMap[$attribute] ) && $dataMap[$attribute]->dataType()->DataTypeString != 'ngindexer' )
                                    {
                                        $handler = ezfSolrDocumentFieldBase::getInstance( $dataMap[$attribute] );
                                        $handlerData[] = $handler->getData();
                                    }
                                }

                                foreach ( $handlerData as $dataFields )
                                {
                                    foreach ( $dataFields as $dataFieldKey => $dataFieldValue )
                                    {
                                        $fieldType = '____t';
                                        $fieldTypePosition = strrpos( $dataFieldKey, '_' );
                                        if ( $fieldTypePosition !== false && $fieldTypePosition + 1 != strlen( $dataFieldKey ) )
                                            $fieldType = '____' . substr( $dataFieldKey, $fieldTypePosition + 1);

                                        $fieldName = self::NGINDEXER_FIELD_PREFIX . $ngIndexerFieldName . '_' . $dataFieldKey . $fieldType;

                                        if ( !isset( $data[$fieldName] ) )
                                            $data[$fieldName] = array();

                                        if ( is_array( $dataFieldValue ) )
                                            $data[$fieldName] = array_merge( $data[$fieldName], $dataFieldValue );
                                        else
                                            $data[$fieldName][] = $dataFieldValue;
                                    }
                                }
                            } break;
                            default: continue;
                        }
                    }
                }
            }
        }

        return $data;
    }
}

?>
