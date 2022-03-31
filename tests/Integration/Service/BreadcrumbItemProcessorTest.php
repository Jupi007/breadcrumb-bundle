<?php
declare(strict_types=1);

namespace SlopeIt\Tests\BreadcrumbBundle\Integration\Service;

use SlopeIt\BreadcrumbBundle\Model\BreadcrumbItem;
use SlopeIt\BreadcrumbBundle\Service\BreadcrumbItemProcessor;
use SlopeIt\Tests\BreadcrumbBundle\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BreadcrumbItemProcessorTest extends KernelTestCase
{
    protected static function getKernelClass()
    {
        return TestKernel::class;
    }

    public function test_process_item_with_reused_path_parameters()
    {
        // Preconditions
        // NOTE: see `tests/Fixtures/config/routing.yml`
        $request = Request::create('http://localhost/parent-path/foo/bar');

        // This is done by the HttpKernel when a request is sent
        $requestStack = self::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        // This framework listener is responsible for setting path parameters as request attributes
        $listener = self::getContainer()->get('router_listener');
        $listener->onKernelRequest(new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        // Action
        /** @var BreadcrumbItemProcessor $SUT */
        $SUT = self::getContainer()->get('slope_it.breadcrumb.item_processor');
        $processedBreadcrumbItems = $SUT->process(
            [
                new BreadcrumbItem('Parent page', 'parent_route'),
                new BreadcrumbItem('Child page', 'child_route', ['component3' => 'baz']),
            ],
            []
        );

        // Verification: url of parent route was reconstructed as is, without needing to specify "component1" and
        // "component2" because already present.
        $this->assertSame('/parent-path/foo/bar', $processedBreadcrumbItems[0]->getUrl());

        // Verification: url of child route was constructed by implicitly reusing "component1" from current, parent path
        // "component3" was explicitly provided in the breadcrum item.
        // "component2" was ignored as not present in child path.
        $this->assertSame('/parent-path/foo/child-path/baz', $processedBreadcrumbItems[1]->getUrl());
    }
}
