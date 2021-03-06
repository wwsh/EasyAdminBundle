<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Tests\Controller;

use Symfony\Component\DomCrawler\Crawler;
use JavierEguiluz\Bundle\EasyAdminBundle\Tests\Fixtures\AbstractTestCase;

class DefaultBackendTest extends AbstractTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->initClient(array('environment' => 'default_backend'));
    }

    public function testBackendHomepageRedirection()
    {
        $this->client->request('GET', '/admin/');

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            '/admin/?action=list&entity=Category',
            $this->client->getResponse()->getTargetUrl(),
            'The backend homepage redirects to the "list" view of the first configured entity ("Category").'
        );
    }

    public function testLanguageDefinedByLayout()
    {
        $crawler = $this->getBackendHomepage();

        $this->assertEquals('en', trim($crawler->filter('html')->attr('lang')));
    }

    public function testDefaultCssFilesAreLinked()
    {
        $cssFiles = array(
            '/bundles/easyadmin/stylesheet/bootstrap.min.css',
            '/bundles/easyadmin/stylesheet/font-awesome.min.css',
            '/bundles/easyadmin/stylesheet/adminlte.min.css',
            '/bundles/easyadmin/stylesheet/featherlight.min.css',
            '/admin/_css/easyadmin.css',
        );

        $crawler = $this->getBackendHomepage();

        foreach ($cssFiles as $i => $url) {
            $this->assertEquals($url, $crawler->filterXPath('//link[@rel="stylesheet"]')->eq($i)->attr('href'));
        }
    }

    public function testLogo()
    {
        $crawler = $this->getBackendHomepage();

        $this->assertEquals('E', $crawler->filter('#header-logo a .logo-mini')->text());
        $this->assertEquals('EasyAdmin', $crawler->filter('#header-logo a .logo-lg')->text());
        $this->assertEquals('/admin/', $crawler->filter('#header-logo a')->attr('href'));
    }

    public function testMainMenuItems()
    {
        $menuItems = array(
            'Category' => '/admin/?entity=Category&action=list&menuIndex=0&submenuIndex=-1',
            'Image' => '/admin/?entity=Image&action=list&menuIndex=1&submenuIndex=-1',
            'Purchase' => '/admin/?entity=Purchase&action=list&menuIndex=2&submenuIndex=-1',
            'PurchaseItem' => '/admin/?entity=PurchaseItem&action=list&menuIndex=3&submenuIndex=-1',
            'Product' => '/admin/?entity=Product&action=list&menuIndex=4&submenuIndex=-1',
        );

        $crawler = $this->getBackendHomepage();

        $i = 0;
        foreach ($menuItems as $label => $url) {
            $this->assertEquals($label, trim($crawler->filter('.sidebar-menu li a')->eq($i)->text()));
            $this->assertEquals($url, $crawler->filter('.sidebar-menu li a')->eq($i)->attr('href'));

            ++$i;
        }
    }

    public function testAdminCssFile()
    {
        $this->client->request('GET', '/admin/_css/easyadmin.css');

        $this->assertEquals('text/css; charset=UTF-8', $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(13, substr_count($this->client->getResponse()->getContent(), '#205081'), 'The admin.css file uses the default brand color.');
        // #222222 color is only used by the "dark" color scheme, not the "light" one
        $this->assertEquals(7, substr_count($this->client->getResponse()->getContent(), '#222222'), 'The admin.css file uses the dark color scheme.');
    }

    public function testListViewTitle()
    {
        $crawler = $this->requestListView();

        $this->assertEquals('Category', trim($crawler->filter('head title')->text()));
        $this->assertEquals('Category', trim($crawler->filter('h1.title')->text()));
    }

    public function testListViewSearchAction()
    {
        $crawler = $this->requestListView();

        $hiddenParameters = array(
            'action' => 'search',
            'entity' => 'Category',
            'sortField' => 'id',
            'sortDirection' => 'DESC',
        );

        $this->assertEquals('Search', trim($crawler->filter('.action-search button[type=submit]')->text()));

        $i = 0;
        foreach ($hiddenParameters as $name => $value) {
            $this->assertEquals($name, $crawler->filter('.action-search input[type=hidden]')->eq($i)->attr('name'));
            $this->assertEquals($value, $crawler->filter('.action-search input[type=hidden]')->eq($i)->attr('value'));

            ++$i;
        }
    }

    public function testListViewNewAction()
    {
        $crawler = $this->requestListView();

        $this->assertEquals('Add Category', trim($crawler->filter('.global-actions a.btn')->text()));
        $this->assertCount(0, $crawler->filter('.global-actions a.btn i'), 'The default "new" button shows no icon.');
        $this->assertStringStartsWith('/admin/?action=new&entity=Category&sortField=id&sortDirection=DESC&page=1', $crawler->filter('.global-actions a.btn')->attr('href'));
    }

    public function testListViewItemActions()
    {
        $crawler = $this->requestListView();

        $this->assertEquals('Edit', trim($crawler->filter('#main .table td.actions a')->eq(0)->text()));
        $this->assertEquals('Delete', trim($crawler->filter('#main .table td.actions a')->eq(1)->text()));
    }

    public function testListViewTableIdColumn()
    {
        $crawler = $this->requestListView();

        $this->assertEquals('ID', trim($crawler->filter('table th[data-property-name="id"]')->text()),
            'The ID entity property is very special and we uppercase it automatically to improve its readability.'
        );
    }

    public function testListViewTableColumnLabels()
    {
        $crawler = $this->requestListView();
        $columnLabels = array('ID', 'Name', 'Products', 'Parent', 'Actions');

        foreach ($columnLabels as $i => $label) {
            $this->assertEquals($label, trim($crawler->filter('.table thead th')->eq($i)->text()));
        }
    }

    public function testListViewTableColumnAttributes()
    {
        $crawler = $this->requestListView();
        $columnAttributes = array('id', 'name', 'products', 'parent');

        foreach ($columnAttributes as $i => $attribute) {
            $this->assertEquals($attribute, trim($crawler->filter('.table thead th')->eq($i)->attr('data-property-name')));
        }
    }

    public function testListViewDefaultTableSorting()
    {
        $crawler = $this->requestListView();

        $this->assertCount(1, $crawler->filter('.table thead th[class*="sorted"]'), 'Table is sorted only by one column.');
        $this->assertEquals('ID', trim($crawler->filter('.table thead th[class*="sorted"]')->text()), 'By default, table is soreted by ID column.');
        $this->assertEquals('fa fa-caret-down', $crawler->filter('.table thead th[class*="sorted"] i')->attr('class'), 'The column used to sort results shows the right icon.');
    }

    public function testListViewTableContents()
    {
        $crawler = $this->requestListView();

        $this->assertCount(15, $crawler->filter('.table tbody tr'));
    }

    public function testListViewTableRowAttributes()
    {
        $crawler = $this->requestListView();
        $columnAttributes = array('ID', 'Name', 'Products', 'Parent');

        foreach ($columnAttributes as $i => $attribute) {
            $this->assertEquals($attribute, trim($crawler->filter('.table tbody tr td')->eq($i)->attr('data-label')));
        }
    }

    public function testListViewPagination()
    {
        $crawler = $this->requestListView();

        $this->assertContains('1 - 15 of 200', $crawler->filter('.list-pagination')->text());

        $this->assertEquals('disabled', $crawler->filter('.list-pagination li:contains("First")')->attr('class'));
        $this->assertEquals('disabled', $crawler->filter('.list-pagination li:contains("Previous")')->attr('class'));

        $this->assertStringStartsWith('/admin/?action=list&entity=Category&sortField=id&sortDirection=DESC&page=2', $crawler->filter('.list-pagination li a:contains("Next")')->attr('href'));
        $this->assertStringStartsWith('/admin/?action=list&entity=Category&sortField=id&sortDirection=DESC&page=14', $crawler->filter('.list-pagination li a:contains("Last")')->attr('href'));
    }

    public function testShowViewPageTitle()
    {
        $crawler = $this->requestShowView();

        $this->assertEquals('Category (#200)', trim($crawler->filter('head title')->text()));
        $this->assertEquals('Category (#200)', trim($crawler->filter('h1.title')->text()));
    }

    public function testShowViewFieldLabels()
    {
        $crawler = $this->requestShowView();
        $fieldLabels = array('ID', 'Name', 'Products', 'Parent');

        foreach ($fieldLabels as $i => $label) {
            $this->assertEquals($label, trim($crawler->filter('#main .form-group label')->eq($i)->text()));
        }
    }

    public function testShowViewFieldClasses()
    {
        $crawler = $this->requestShowView();
        $fieldClasses = array('integer', 'string', 'association');

        foreach ($fieldClasses as $i => $cssClass) {
            $this->assertContains('field-'.$cssClass, trim($crawler->filter('#main .form-group')->eq($i)->attr('class')));
        }
    }

    public function testShowViewActions()
    {
        $crawler = $this->requestShowView();

        // edit action
        $this->assertContains('fa-edit', trim($crawler->filter('.form-actions a:contains("Edit") i')->attr('class')));

        // delete action
        $this->assertContains('fa-trash', trim($crawler->filter('.form-actions a:contains("Delete") i')->attr('class')));

        // list action
        $this->assertEquals('btn btn-secondary action-list', trim($crawler->filter('.form-actions a:contains("Back to listing")')->attr('class')));
    }

    public function testShowViewReferer()
    {
        $parameters = array(
            'action' => 'list',
            'entity' => 'Category',
            'page' => '2',
            'sortDirection' => 'ASC',
            'sortField' => 'name',
        );

        // 1. visit a specific 'list' view page
        $crawler = $this->getBackendPage($parameters);

        // 2. click on the 'Edit' link of the first item
        $link = $crawler->filter('td.actions a:contains("Edit")')->eq(0)->link();
        $crawler = $this->client->click($link);

        // 3. the 'referer' parameter should point to the exact same previous 'list' page
        $refererUrl = $crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('href');
        $queryString = parse_url($refererUrl, PHP_URL_QUERY);
        parse_str($queryString, $refererParameters);

        $this->assertEquals($parameters, $refererParameters);
    }

    public function testEditViewTitle()
    {
        $crawler = $this->requestEditView();

        $this->assertEquals('Edit Category (#200)', trim($crawler->filter('head title')->text()));
        $this->assertEquals('Edit Category (#200)', trim($crawler->filter('h1.title')->text()));
    }

    public function testEditViewFormAttributes()
    {
        $crawler = $this->requestEditView();
        $form = $crawler->filter('#main form')->eq(0);

        $this->assertSame('edit', trim($form->attr('data-view')));
        $this->assertSame('Category', trim($form->attr('data-entity')));
        $this->assertSame('200', trim($form->attr('data-entity-id')));
    }

    public function testEditViewFieldLabels()
    {
        $crawler = $this->requestEditView();
        $fieldLabels = array('Name', 'Products', 'Parent');

        foreach ($fieldLabels as $i => $label) {
            $this->assertEquals($label, trim($crawler->filter('#main .form-group label')->eq($i)->text()));
        }
    }

    public function testEditViewFieldClasses()
    {
        $crawler = $this->requestEditView();
        $fieldClasses = array('text', 'entity');

        foreach ($fieldClasses as $i => $cssClass) {
            $this->assertContains('field-'.$cssClass, trim($crawler->filter('#main .form-group')->eq($i)->attr('class')));
        }
    }

    public function testEditViewActions()
    {
        $crawler = $this->requestEditView();

        // save action
        $this->assertContains('fa-save', trim($crawler->filter('#form-actions-row button:contains("Save changes") i')->attr('class')));

        // delete action
        $this->assertContains('fa-trash', trim($crawler->filter('#form-actions-row a:contains("Delete") i')->attr('class')));

        // list action
        $this->assertEquals('btn btn-secondary action-list', trim($crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('class')));
    }

    public function testEditViewReferer()
    {
        $parameters = array(
            'action' => 'list',
            'entity' => 'Category',
            'page' => '2',
            'sortDirection' => 'ASC',
            'sortField' => 'name',
        );

        // 1. visit a specific 'list' view page
        $crawler = $this->getBackendPage($parameters);

        // 2. click on the 'Edit' link of the first item
        $link = $crawler->filter('td.actions a:contains("Edit")')->eq(0)->link();
        $crawler = $this->client->click($link);

        // 3. the 'referer' parameter should point to the exact same previous 'list' page
        $refererUrl = $crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('href');
        $queryString = parse_url($refererUrl, PHP_URL_QUERY);
        parse_str($queryString, $refererParameters);

        $this->assertEquals($parameters, $refererParameters);
    }

    public function testEditViewEntityModification()
    {
        $crawler = $this->requestEditView();
        $this->client->followRedirects();

        $categoryName = sprintf('Modified Category %s', md5(rand()));
        $form = $crawler->selectButton('Save changes')->form(array(
            'category[name]' => $categoryName,
        ));
        $crawler = $this->client->submit($form);

        $this->assertContains(
            $categoryName,
            $crawler->filter('#main table tr')->eq(1)->text(),
            'The modified category is displayed in the first data row of the "list" table.'
        );
    }

    public function testEntityModificationViaAjax()
    {
        $em = $this->client->getContainer()->get('doctrine');
        $product = $em->getRepository('AppTestBundle:Product')->find(1);
        $this->assertTrue($product->isEnabled(), 'Initially the product is enabled.');

        $queryParameters = array('action' => 'edit', 'view' => 'list', 'entity' => 'Product', 'id' => '1', 'property' => 'enabled', 'newValue' => 'false');
        $this->client->request('GET', '/admin/?'.http_build_query($queryParameters), array(), array(), array('HTTP_X-Requested-With' => 'XMLHttpRequest'));

        $product = $em->getRepository('AppTestBundle:Product')->find(1);
        $this->assertFalse($product->isEnabled(), 'After editing it via Ajax, the product is not enabled.');
    }

    public function testWrongEntityModificationViaAjax()
    {
        $queryParameters = array('action' => 'edit', 'view' => 'list', 'entity' => 'Product', 'id' => '1', 'property' => 'this_property_does_not_exist', 'newValue' => 'false');
        $this->client->request('GET', '/admin/?'.http_build_query($queryParameters), array(), array(), array('HTTP_X-Requested-With' => 'XMLHttpRequest'));

        $this->assertEquals(500, $this->client->getResponse()->getStatusCode(), 'Trying to modify a non-existent property via Ajax returns a 500 error');
        $this->assertContains('The type of the &quot;this_property_does_not_exist&quot; property is not &quot;toggle&quot;', $this->client->getResponse()->getContent());
    }

    public function testNewViewTitle()
    {
        $crawler = $this->requestNewView();

        $this->assertEquals('Create Category', trim($crawler->filter('head title')->text()));
        $this->assertEquals('Create Category', trim($crawler->filter('h1.title')->text()));
    }

    public function testNewViewFormAttributes()
    {
        $crawler = $this->requestNewView();
        $form = $crawler->filter('#main form')->eq(0);

        $this->assertSame('new', trim($form->attr('data-view')));
        $this->assertSame('Category', trim($form->attr('data-entity')));
        $this->assertEmpty($form->attr('data-entity-id'));
    }

    public function testNewViewFieldLabels()
    {
        $crawler = $this->requestNewView();
        $fieldLabels = array('Name', 'Products', 'Parent');

        foreach ($fieldLabels as $i => $label) {
            $this->assertEquals($label, trim($crawler->filter('#main .form-group label')->eq($i)->text()));
        }
    }

    public function testNewViewFieldClasses()
    {
        $crawler = $this->requestNewView();
        $fieldClasses = array('text', 'entity');

        foreach ($fieldClasses as $i => $cssClass) {
            $this->assertContains('field-'.$cssClass, trim($crawler->filter('#main .form-group')->eq($i)->attr('class')));
        }
    }

    public function testNewViewActions()
    {
        $crawler = $this->requestNewView();

        // save action
        $this->assertContains('fa-save', trim($crawler->filter('#form-actions-row button:contains("Save changes") i')->attr('class')));

        // list action
        $this->assertEquals('btn btn-secondary action-list', trim($crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('class')));
    }

    public function testNewViewReferer()
    {
        $parameters = array(
            'action' => 'list',
            'entity' => 'Category',
            'page' => '2',
            'sortDirection' => 'ASC',
            'sortField' => 'name',
        );

        // 1. visit a specific 'list' view page
        $crawler = $this->getBackendPage($parameters);

        // 2. click on the 'New' link to browse the 'new' view
        $link = $crawler->filter('.global-actions a:contains("Add Category")')->link();
        $crawler = $this->client->click($link);

        // 3. the 'referer' parameter should point to the exact same previous 'list' page
        $refererUrl = $crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('href');
        $queryString = parse_url($refererUrl, PHP_URL_QUERY);
        parse_str($queryString, $refererParameters);

        $this->assertEquals($parameters, $refererParameters);
    }

    public function testNewViewEntityCreation()
    {
        $crawler = $this->requestNewView();
        $this->client->followRedirects();

        $categoryName = sprintf('The New Category %s', md5(rand()));
        $form = $crawler->selectButton('Save changes')->form(array(
            'category[name]' => $categoryName,
        ));
        $crawler = $this->client->submit($form);

        $this->assertContains($categoryName, $crawler->filter('#main table tr')->eq(1)->text(), 'The newly created category is displayed in the first data row of the "list" table.');
    }

    public function testSearchViewTitle()
    {
        $crawler = $this->requestSearchView();

        $this->assertEquals('200 results found', trim($crawler->filter('head title')->html()), 'The page title does not contain HTML tags.');
        $this->assertEquals('<strong>200</strong> results found', trim($crawler->filter('h1.title')->html()), 'The visible content contains HTML tags.');
    }

    public function testSearchViewTableIdColumn()
    {
        $crawler = $this->requestSearchView();

        $this->assertEquals('ID', trim($crawler->filter('table th[data-property-name="id"]')->text()),
            'The ID entity property is very special and we uppercase it automatically to improve its readability.'
        );
    }

    public function testSearchViewTableColumnLabels()
    {
        $crawler = $this->requestSearchView();
        $columnLabels = array('ID', 'Name', 'Products', 'Parent', 'Actions');

        foreach ($columnLabels as $i => $label) {
            $this->assertEquals($label, trim($crawler->filter('.table thead th')->eq($i)->text()));
        }
    }

    public function testSearchViewTableColumnAttributes()
    {
        $crawler = $this->requestSearchView();
        $columnAttributes = array('id', 'name', 'products', 'parent');

        foreach ($columnAttributes as $i => $attribute) {
            $this->assertEquals($attribute, trim($crawler->filter('.table thead th')->eq($i)->attr('data-property-name')));
        }
    }

    public function testSearchViewDefaultTableSorting()
    {
        $crawler = $this->requestSearchView();

        $this->assertCount(1, $crawler->filter('.table thead th[class*="sorted"]'), 'Table is sorted only by one column.');
        $this->assertEquals('ID', trim($crawler->filter('.table thead th[class*="sorted"]')->text()), 'By default, table is soreted by ID column.');
        $this->assertEquals('fa fa-caret-down', $crawler->filter('.table thead th[class*="sorted"] i')->attr('class'), 'The column used to sort results shows the right icon.');
    }

    public function testSearchViewTableContents()
    {
        $crawler = $this->requestSearchView();

        $this->assertCount(15, $crawler->filter('.table tbody tr'));
    }

    public function testSearchViewTableRowAttributes()
    {
        $crawler = $this->requestSearchView();
        $columnAttributes = array('ID', 'Name', 'Products', 'Parent');

        foreach ($columnAttributes as $i => $attribute) {
            $this->assertEquals($attribute, trim($crawler->filter('.table tbody tr td')->eq($i)->attr('data-label')));
        }
    }

    public function testSearchViewPagination()
    {
        $crawler = $this->requestSearchView();

        $this->assertContains('1 - 15 of 200', $crawler->filter('.list-pagination')->text());

        $this->assertEquals('disabled', $crawler->filter('.list-pagination li:contains("First")')->attr('class'));
        $this->assertEquals('disabled', $crawler->filter('.list-pagination li:contains("Previous")')->attr('class'));

        $this->assertStringStartsWith('/admin/?action=search&entity=Category&sortField=id&sortDirection=DESC&page=2', $crawler->filter('.list-pagination li a:contains("Next")')->attr('href'));
        $this->assertStringStartsWith('/admin/?action=search&entity=Category&sortField=id&sortDirection=DESC&page=14', $crawler->filter('.list-pagination li a:contains("Last")')->attr('href'));
    }

    public function testSearchViewItemActions()
    {
        $crawler = $this->requestSearchView();

        $this->assertEquals('Edit', trim($crawler->filter('#main .table td.actions a')->eq(0)->text()));
        $this->assertEquals('Delete', trim($crawler->filter('#main .table td.actions a')->eq(1)->text()));
    }

    public function testSearchViewShowActionReferer()
    {
        $parameters = array(
            'action' => 'search',
            'entity' => 'Category',
            'page' => '2',
            'query' => 'cat',
            'sortDirection' => 'ASC',
            'sortField' => 'name',
        );

        // 1. visit a specific 'search' view page
        $crawler = $this->getBackendPage($parameters);

        // 2. click on the 'Edit' action of the first result
        $link = $crawler->filter('td.actions a:contains("Edit")')->eq(0)->link();
        $crawler = $this->client->click($link);

        // 3. the 'referer' parameter should point to the exact same previous 'list' page
        $refererUrl = $crawler->filter('#form-actions-row a:contains("Back to listing")')->attr('href');
        $queryString = parse_url($refererUrl, PHP_URL_QUERY);
        parse_str($queryString, $refererParameters);

        $this->assertEquals($parameters, $refererParameters);
    }

    public function testEntityDeletion()
    {
        $em = $this->client->getContainer()->get('doctrine');
        $product = $em->getRepository('AppTestBundle:Product')->find(1);
        $this->assertNotNull($product, 'Initially the product exists.');

        $crawler = $this->requestEditView('Product', '1');
        $this->client->followRedirects();
        $form = $crawler->filter('#delete_form_submit')->form();
        $this->client->submit($form);

        $product = $em->getRepository('AppTestBundle:Product')->find(1);
        $this->assertNull($product, 'After removing it via the delete form, the product no longer exists.');
    }

    public function testEntityDeletionRequiresCsrfToken()
    {
        $queryParameters = array('action' => 'delete', 'entity' => 'Product', 'id' => '1');
        // Sending a 'DELETE' HTTP request is not enough (the delete form includes a CSRF token)
        $this->client->request('DELETE', '/admin/?'.http_build_query($queryParameters));

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Redirecting to /admin/?action=list&amp;entity=Product', $this->client->getResponse()->getContent());
    }

    public function testEntityDeletionRequiresDeleteHttpMethod()
    {
        $queryParameters = array('action' => 'delete', 'entity' => 'Product', 'id' => '1');
        // 'POST' HTTP method is wrong for deleting entities ('DELETE' method is required)
        $this->client->request('POST', '/admin/?'.http_build_query($queryParameters));

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Redirecting to /admin/?action=list&amp;entity=Product', $this->client->getResponse()->getContent());
    }

    /**
     * @return Crawler
     */
    private function requestListView()
    {
        return $this->getBackendPage(array(
            'action' => 'list',
            'entity' => 'Category',
        ));
    }

    /**
     * @return Crawler
     */
    private function requestShowView()
    {
        return $this->getBackendPage(array(
            'action' => 'show',
            'entity' => 'Category',
            'id' => '200',
        ));
    }

    /**
     * @return Crawler
     */
    private function requestEditView($entityName = 'Category', $entityId = '200')
    {
        return $this->getBackendPage(array(
            'action' => 'edit',
            'entity' => $entityName,
            'id' => $entityId,
        ));
    }

    /**
     * @return Crawler
     */
    private function requestNewView()
    {
        return $this->getBackendPage(array(
            'action' => 'new',
            'entity' => 'Category',
        ));
    }

    /**
     * @return Crawler
     */
    private function requestSearchView()
    {
        return $this->getBackendPage(array(
            'action' => 'search',
            'entity' => 'Category',
            'query' => 'cat',
        ));
    }
}
