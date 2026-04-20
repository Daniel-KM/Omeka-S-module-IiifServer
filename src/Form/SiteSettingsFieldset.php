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
                    'label' => 'Resource block IIIF Player: Viewer', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'diva' => 'Diva (module)', // @translate
                        'mirador' => 'Mirador (module)', // @translate
                        'mirador_core' => 'Mirador (Omeka)', // @translate
                        'openseadragon' => 'OpenSeadragon', // @translate
                        'universalviewer' => 'Universal Viewer (module)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_player',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player_osd_sidebar',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player: OpenSeadragon thumbnails sidebar', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'bottom' => 'Bottom', // @translate
                        'top' => 'Top', // @translate
                        'left' => 'Left', // @translate
                        'right' => 'Right', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_osd_sidebar',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player_inline_height',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Viewer: Inline height', // @translate
                    'info' => 'CSS length (e.g. 600px, 80vh, 100%).', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_inline_height',
                    'placeholder' => '600px',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player_button_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Viewer Button: Label', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_button_label',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player_button_lazy',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player Button: Lazy load', // @translate
                    'info' => 'When enabled, the viewer is loaded only on the first click. The options may conflict with a pre-existing instance of the same viewer on the page.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_button_lazy',
                ],
            ])
        ;
    }
}
