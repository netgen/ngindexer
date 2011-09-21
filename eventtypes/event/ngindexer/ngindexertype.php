<?php

class ngIndexerEventType extends eZWorkflowEventType
{
    const EZ_WORKFLOW_TYPE_STRING = "ngindexer";

    public function __construct()
    {
        parent::__construct(self::EZ_WORKFLOW_TYPE_STRING, "Netgen Indexer");
    }

    public function execute( $process, $event )
    {
        $processParameters = $process->attribute( 'parameter_list' );
        $object = eZContentObject::fetch( $processParameters['object_id'] );
        $targetClassIdentifier = $object->contentClass()->attribute( 'identifier' );
        $iniGroups = eZINI::instance( 'ngindexer.ini' )->groups();
        $targets = array();

        foreach ( $iniGroups as $iniGroupName => $iniGroup )
        {
            $iniGroupNameArray = explode( '/', $iniGroupName );
            if ( is_array( $iniGroupNameArray ) && count( $iniGroupNameArray ) == 3 )
            {
                $class = eZContentClass::fetchByIdentifier( $iniGroupNameArray[0] );
                if ( $class instanceof eZContentClass )
                {
                    $classAttribute = $class->fetchAttributeByIdentifier( $iniGroupNameArray[1] );
                    if ( $classAttribute instanceof eZContentClassAttribute )
                    {
                        $indexTarget = isset( $iniGroup['IndexTarget'] ) ? trim( $iniGroup['IndexTarget'] ) : '';
                        $referencedClassIdentifiers = isset( $iniGroup['ClassIdentifiers'] ) && is_array( $iniGroup['ClassIdentifiers'] ) ? $iniGroup['ClassIdentifiers'] : null;

                        if ( $referencedClassIdentifiers != null &&
                            ( empty( $referencedClassIdentifiers ) || in_array( $targetClassIdentifier, $referencedClassIdentifiers ) ) )
                        {
                            switch ( $indexTarget )
                            {
                                case 'parent':
                                case 'parent_line':
                                {
                                    $children = eZContentObjectTreeNode::subTreeByNodeID( array(
                                                    'ClassFilterType' => 'include',
                                                    'ClassFilterArray' => array( $iniGroupNameArray[0] ),
                                                    'Depth' => $indexTarget == 'parent' ? 1 : false
                                                ), $object->mainNode()->attribute( 'node_id' ) );

                                    if ( is_array( $children ) )
                                        $targets = array_merge( $targets, $children );
                                } break;
                                case 'children':
                                {
                                    $parentNode = $object->mainNode()->fetchParent();

                                    if ( $parentNode->classIdentifier() == $iniGroupNameArray[0] )
                                        $targets[] = $parentNode;
                                } break;
                                case 'subtree':
                                {
                                    $path = $object->mainNode()->fetchPath();

                                    foreach ( $path as $pathNode )
                                    {
                                        if ( $pathNode->classIdentifier() == $iniGroupNameArray[0] )
                                            $targets[] = $parentNode;
                                    }
                                } break;
                                default: continue;
                            }
                        }
                    }
                }
            }
        }

        $filteredTargets = array();
        foreach ( $targets as $target )
        {
            $objectID = $target->attribute( 'contentobject_id' );
            $version = $target->object()->attribute( 'current_version' );

            if ( !isset( $filteredTargets[$objectID] ) )
            {
                $filteredTargets[$objectID] = $version;
                eZContentOperationCollection::registerSearchObject( $objectID, $version );
            }
        }

        return eZWorkflowType::STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerEventType(ngIndexerEventType::EZ_WORKFLOW_TYPE_STRING, 'ngindexereventtype');

?>
