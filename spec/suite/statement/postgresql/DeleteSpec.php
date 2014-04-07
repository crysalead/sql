<?php
namespace sql\spec\suite\statement\mysql;

use sql\dialect\PostgreSql;

describe("PostgreSql Delete", function() {

    beforeEach(function() {
        $this->dialect = new PostgreSql();
        $this->delete = $this->dialect->statement('delete');
    });

    describe("->returning()", function() {

        it("sets `RETURNING`", function() {

            $this->delete->from('table')->returning('*');
            expect($this->delete->toString())->toBe('DELETE FROM "table" RETURNING *');

        });

    });

});