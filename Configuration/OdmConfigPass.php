<?php

namespace JavierEguiluz\Bundle\EasyAdminBundle\Configuration;

/**
 * Providing support the the 'documents' config entry.
 * The entities are an abstract construct, hence all logic is applied to ODM
 * documents as they were entities. Thanks to Javier for the tip!
 *
 * @author Thomas Parys <thomas.parys@wwsh.io>
 * 
 * By default the entity name is used as its label (showed in buttons, the
 * main menu, etc.). That's why the config format #3 can optionally define
 * a custom entity label
 *
 * easy_admin:
 *     entities:
 *         User:
 *             class: AppBundle\Entity\User
 *             label: 'Clients'
 *
 * For documents:
 *
 * # Config format #1: no custom document name
 * easy_admin:
 *     documents:
 *         - AppBundle\Document\User
 *
 * # Config format #2: simple config with custom document name
 * easy_admin:
 *     documents:
 *         User: AppBundle\Document\User
 *
 */
class OdmConfigPass implements ConfigPassInterface
{
    /**
     * Mapping element types to Doctrine service bundle class names.
     * @var array
     */
    protected $dataServices = [
        'entities'  => 'dataservice_orm',
        'documents' => 'dataservice_mongo',
    ];

    /**
     * For an easy and unified processing, documents should not differ
     * from their entities counterparts.
     * From now on merged as one.
     *
     * @param $backendConfig
     * @return mixed
     */
    private function processMergeDocumentsIntoEntities($backendConfig)
    {
        if (isset($backendConfig['entities'])) {
            foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
                // normalize config formats #1 and #2 to use the 'class' option as config format #3
                if (!is_array($entityConfig)) {
                    $entityConfig = array('class' => $entityConfig);
                }

                $entityConfig['data_manager_service']
                    = $this->dataServices['entities'];

                $backendConfig['entities'][$entityName] = $entityConfig;
            }
        }

        if (isset($backendConfig['documents'])) {
            foreach ($backendConfig['documents'] as $entityName => $entityConfig) {
                // normalize config formats #1 and #2 to use the 'class' option as config format #3
                if (!is_array($entityConfig)) {
                    $entityConfig = array('class' => $entityConfig);
                }

                $entityConfig['data_manager_service']
                    = $this->dataServices['documents'];

                $backendConfig['documents'][$entityName] = $entityConfig;
            }
        }

        if (!isset($backendConfig['documents'])) {
            return $backendConfig;
        }

        $backendConfig['entities'] =
            $backendConfig['entities'] +
            $backendConfig['documents'];

        unset($backendConfig['documents']);

        // rekey
        $backendConfig['entities'] = array_values($backendConfig['entities']);

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        $backendConfig = $this->processMergeDocumentsIntoEntities($backendConfig);

        return $backendConfig;
    }
}