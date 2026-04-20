<?php declare(strict_types=1);

namespace IiifServer\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Players'; // @translate

    protected $elementGroups = [
        'player' => 'Players', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'iiif-server')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'iiifserver_player',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player Button: Viewer', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'diva' => 'Diva (module)', // @translate
                        'mirador' => 'Mirador (module)', // @translate
                        'universalviewer' => 'Universal Viewer (module)', // @translate
                        'mirador_core' => 'Mirador (Omeka)', // @translate
                        'openseadragon_core' => 'OpenSeadragon (Omeka)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_player',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player_button_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player Button: Label', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_button_label',
                ],
            ])
        ;
    }
}
