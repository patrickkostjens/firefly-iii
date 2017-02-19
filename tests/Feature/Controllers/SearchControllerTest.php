<?php
/**
 * SearchControllerTest.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 * This software may be modified and distributed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace Tests\Feature\Controllers;

use FireflyIII\Support\Search\SearchInterface;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{

    /**
     * @covers \FireflyIII\Http\Controllers\SearchController::index
     * Implement testIndex().
     */
    public function testIndex()
    {
        $search = $this->mock(SearchInterface::class);
        $search->shouldReceive('setLimit')->once();
        $search->shouldReceive('parseQuery')->once();
        $search->shouldReceive('hasModifiers')->once()->andReturn(false);
        $search->shouldReceive('getWordsAsString')->once()->andReturn('test');
        $search->shouldReceive('searchTransactions')->andReturn(new Collection)->once();
        $search->shouldReceive('searchBudgets')->andReturn(new Collection)->once();
        $search->shouldReceive('searchTags')->andReturn(new Collection)->once();
        $search->shouldReceive('searchCategories')->andReturn(new Collection)->once();
        $search->shouldReceive('searchAccounts')->andReturn(new Collection)->once();
        $this->be($this->user());
        $response = $this->get(route('search.index') . '?q=test');
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

}
