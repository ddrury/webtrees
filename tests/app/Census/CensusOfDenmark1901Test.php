<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Census;

use Fisharebest\Webtrees\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;


#[CoversClass(CensusOfDenmark1901::class)]
#[CoversClass(AbstractCensusColumn::class)]
class CensusOfDenmark1901Test extends TestCase
{
    /**
     * Test the census place and date
     */
    public function testPlaceAndDate(): void
    {
        $census = new CensusOfDenmark1901();

        self::assertSame('Danmark', $census->censusPlace());
        self::assertSame('01 FEB 1901', $census->censusDate());
    }

    /**
     * Test the census columns
     */
    public function testColumns(): void
    {
        $census  = new CensusOfDenmark1901();
        $columns = $census->columns();

        self::assertCount(17, $columns);
        self::assertInstanceOf(CensusColumnFullName::class, $columns[0]);
        self::assertInstanceOf(CensusColumnSexMK::class, $columns[1]);
        self::assertInstanceOf(CensusColumnBirthDaySlashMonthYear::class, $columns[2]);
        self::assertInstanceOf(CensusColumnConditionDanish::class, $columns[3]);
        self::assertInstanceOf(CensusColumnReligion::class, $columns[4]);
        self::assertInstanceOf(CensusColumnBirthPlace::class, $columns[5]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[6]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[7]);
        self::assertInstanceOf(CensusColumnRelationToHead::class, $columns[8]);
        self::assertInstanceOf(CensusColumnOccupation::class, $columns[9]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[10]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[11]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[12]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[13]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[14]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[15]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[16]);

        self::assertSame('Navn', $columns[0]->abbreviation());
        self::assertSame('Køn', $columns[1]->abbreviation());
        self::assertSame('Fødselsdag', $columns[2]->abbreviation());
        self::assertSame('Civilstand', $columns[3]->abbreviation());
        self::assertSame('Trossamfund', $columns[4]->abbreviation());
        self::assertSame('Fødested', $columns[5]->abbreviation());
        self::assertSame('', $columns[6]->abbreviation());
        self::assertSame('', $columns[7]->abbreviation());
        self::assertSame('Stilling i familien', $columns[8]->abbreviation());
        self::assertSame('Erhverv', $columns[9]->abbreviation());
        self::assertSame('', $columns[10]->abbreviation());
        self::assertSame('', $columns[11]->abbreviation());
        self::assertSame('', $columns[12]->abbreviation());
        self::assertSame('', $columns[13]->abbreviation());
        self::assertSame('', $columns[14]->abbreviation());
        self::assertSame('', $columns[15]->abbreviation());
        self::assertSame('Anmærkninger', $columns[16]->abbreviation());

        self::assertSame('Samtlige Personers Navn (ogsaa Fornavn). Ved Børn, endnu uden Navn, sættes „Dreng“ eller „Pige“.', $columns[0]->title());
        self::assertSame('Kjønnet. Mandkøn (M.) eller Kvindekøn (Kv.).', $columns[1]->title());
        self::assertSame('Føderlsaar og Føderladag.', $columns[2]->title());
        self::assertSame('Ægteskabelig Stillinge. Ugift (U.), Gift (G.), Enkemand eller Enke (E.), Separeret (S.), Fraskilt (F.).', $columns[3]->title());
        self::assertSame('Trossamfund (Folkekirken eller Navnet paa det Trossamfund, man tilhører, eller „uden for Trossamfund“).', $columns[4]->title());
        self::assertSame('Fødested 1) Indenlandsk Fødested: Kebstadens, Handelspladsens eller Sogneta og Amtets Navn (kan Amtet ikke angives, sættes vedkommende Landsdel, f. Eks. Fyn, Jlland osv.), 2) Fedt i Bilandene eller Udlandet: Landets Navn.', $columns[5]->title());
        self::assertSame('', $columns[6]->title());
        self::assertSame('', $columns[7]->title());
        self::assertSame('Stilling i Familien (Husfader, Husmoder, Barn, Slangtning o.l., Tjenestetyende (naar vedkommende har Skudsmaalsbog), Pensioner, logerende.', $columns[8]->title());
        self::assertSame('', $columns[9]->title());
        self::assertSame('', $columns[10]->title());
        self::assertSame('', $columns[11]->title());
        self::assertSame('', $columns[12]->title());
        self::assertSame('', $columns[13]->title());
        self::assertSame('', $columns[14]->title());
        self::assertSame('', $columns[15]->title());
        self::assertSame('Anmærkninger.', $columns[16]->title());
    }
}
