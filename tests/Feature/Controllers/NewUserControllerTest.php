<?php
/**
 * NewUserControllerTest.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 * This software may be modified and distributed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace Tests\Feature\Controllers;

use Tests\TestCase;

class NewUserControllerTest extends TestCase
{

    /**
     * @covers \FireflyIII\Http\Controllers\NewUserController::index
     * @covers \FireflyIII\Http\Controllers\NewUserController::__construct
     */
    public function testIndex()
    {
        $this->be($this->emptyUser());
        $response = $this->get(route('new-user.index'));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\NewUserController::submit
     */
    public function testSubmit()
    {
        $data = [
            'bank_name'    => 'New bank',
            'bank_balance' => 100,
        ];
        $this->be($this->emptyUser());
        $response = $this->post(route('new-user.submit'), $data);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

}
