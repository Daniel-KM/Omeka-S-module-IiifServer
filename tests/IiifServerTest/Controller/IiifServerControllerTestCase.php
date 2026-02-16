<?php declare(strict_types=1);

namespace IiifServerTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;

abstract class IiifServerControllerTestCase extends AbstractHttpControllerTestCase
{
    /**
     * @var int[]
     */
    protected $createdItemIds = [];

    /**
     * @var int[]
     */
    protected $createdItemSetIds = [];

    /**
     * Override to always fetch a fresh User entity from the current entity
     * manager, avoiding detached entity issues after dispatch() resets.
     */
    protected function loginAsAdmin(): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        $em = $services->get('Omeka\EntityManager');
        $user = $em->getRepository(\Omeka\Entity\User::class)
            ->findOneBy(['email' => $this->adminEmail]);
        if ($user) {
            $auth->getStorage()->write($user);
        }
    }

    public function tearDown(): void
    {
        // Re-authenticate since dispatch() may have reset the application.
        $this->loginAsAdmin();
        foreach ($this->createdItemIds as $id) {
            try {
                $this->api()->delete('items', $id);
            } catch (\Exception $e) {
                // Already deleted.
            }
        }
        foreach ($this->createdItemSetIds as $id) {
            try {
                $this->api()->delete('item_sets', $id);
            } catch (\Exception $e) {
                // Already deleted.
            }
        }
        $this->createdItemIds = [];
        $this->createdItemSetIds = [];
    }

    /**
     * Create a bare item.
     *
     * @return \Omeka\Api\Representation\ItemRepresentation
     */
    protected function createItem()
    {
        $this->loginAsAdmin();
        $response = $this->api()->create('items');
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();
        return $item;
    }

    /**
     * Create an item with dcterms metadata.
     *
     * @return \Omeka\Api\Representation\ItemRepresentation
     */
    protected function createItemWithMetadata(
        string $title = 'Test Title',
        ?string $description = 'A test description',
        ?string $creator = 'Test Creator'
    ) {
        $this->loginAsAdmin();
        $data = [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
        ];
        if ($description !== null) {
            $data['dcterms:description'] = [
                [
                    'type' => 'literal',
                    'property_id' => 4,
                    '@value' => $description,
                ],
            ];
        }
        if ($creator !== null) {
            $data['dcterms:creator'] = [
                [
                    'type' => 'literal',
                    'property_id' => 2,
                    '@value' => $creator,
                ],
            ];
        }
        $response = $this->api()->create('items', $data);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();
        return $item;
    }

    /**
     * Create an item with HTML media (no external fetch needed).
     *
     * @return \Omeka\Api\Representation\ItemRepresentation
     */
    protected function createItemWithHtmlMedia(
        string $title = 'Item with media',
        int $mediaCount = 1
    ) {
        $this->loginAsAdmin();
        $data = [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
            'o:media' => [],
        ];
        for ($i = 1; $i <= $mediaCount; $i++) {
            $data['o:media'][] = [
                'o:ingester' => 'html',
                'o:source' => 'test-' . $i,
                'html' => '<p>Test content ' . $i . '</p>',
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => 1,
                        '@value' => 'Media ' . $i,
                    ],
                ],
            ];
        }
        $response = $this->api()->create('items', $data);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();
        return $item;
    }

    /**
     * Create a bare item set.
     *
     * @return \Omeka\Api\Representation\ItemSetRepresentation
     */
    protected function createItemSet()
    {
        $this->loginAsAdmin();
        $response = $this->api()->create('item_sets');
        $itemSet = $response->getContent();
        $this->createdItemSetIds[] = $itemSet->id();
        return $itemSet;
    }

    /**
     * Create an item set containing given items.
     *
     * @param int[] $itemIds
     * @return \Omeka\Api\Representation\ItemSetRepresentation
     */
    protected function createItemSetWithItems(array $itemIds, string $title = 'Test Item Set')
    {
        $this->loginAsAdmin();
        $response = $this->api()->create('item_sets', [
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
        ]);
        $itemSet = $response->getContent();
        $this->createdItemSetIds[] = $itemSet->id();

        // Assign items to this item set.
        foreach ($itemIds as $itemId) {
            $this->api()->update('items', $itemId, [
                'o:item_set' => [
                    ['o:id' => $itemSet->id()],
                ],
            ]);
        }

        return $itemSet;
    }
}
