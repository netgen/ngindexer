<?php

class ngIndexerType extends eZDataType
{
    const DATA_TYPE_STRING = 'ngindexer';

    function __construct()
    {
        parent::__construct( self::DATA_TYPE_STRING, 'Netgen Indexer', array( 'translation_allowed' => false ) );
    }

    function isIndexable()
    {
        return true;
    }
}

eZDataType::register( ngIndexerType::DATA_TYPE_STRING, 'ngIndexerType' );

?>
