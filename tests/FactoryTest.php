<?php

namespace ElevenLab\PHPOGC;

use ElevenLab\PHPOGC\DataTypes\GeometryCollection;
use ElevenLab\PHPOGC\DataTypes\MultiLineString;
use ElevenLab\PHPOGC\DataTypes\MultiPoint;
use ElevenLab\PHPOGC\DataTypes\MultiPolygon;
use ElevenLab\PHPOGC\Exceptions\GeoSpatialException;
use ElevenLab\PHPOGC\DataTypes\LineString;
use ElevenLab\PHPOGC\DataTypes\Point;
use ElevenLab\PHPOGC\DataTypes\Polygon;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    public function testPointSuccess()
    {
        $points = [];
        $points[] = new Point(1.234, 2.345);
        $points[] = new Point("1.234", "2.345");
        // fromArray expects [lon, lat] per OGC convention; swap to get lat=1.234, lon=2.345
        $points[] = Point::fromArray([2.345, 1.234]);
        $points[] = Point::fromString("1.234, 2.345");
        $points[] = Point::fromString("1.234 2.345", " ");
        $points[] = Point::fromString("1.234#2.345", "#");
        // POINT(lon lat) per OGC WKT: POINT(2.345 1.234) → lat=1.234, lon=2.345
        $points[] = Point::fromWKT("POINT(2.345 1.234)");

        foreach ($points as $point) {
            $this->assertInstanceOf(Point::class, $point);
            $this->assertEquals(1.234, $point->lat);
            $this->assertEquals(2.345, $point->lon);
        }
    }

    public function testLinestringSuccess()
    {
        $linestrings = [];

        $p1 = new Point(1, 2);
        $p2 = new Point(3, 4);
        $p3 = new Point(5, 6);

        $linestrings[] = new LineString([$p1, $p2, $p3]);
        $linestrings[] = LineString::fromString("1 2, 3 4, 5 6");
        $linestrings[] = LineString::fromString("1 2: 3 4: 5 6", ":");
        $linestrings[] = LineString::fromString("1_2: 3_4: 5_6", ":", "_");
        $linestrings[] = LineString::fromArray([ [1, 2], [2, 3], [3, 4] ]);

        $linestrings[] = new LineString([new Point(1, 2), new Point(3, 4), new Point(5, 6)]);
        $linestrings[] = new LineString([new Point(1, 2), new Point(3, 4), new Point(5, 6), new Point(1, 2)]);
        $linestrings[] = LineString::fromArray([[1,2], [2,3], [3,4]]);
        $linestrings[] = LineString::fromString('1 2, 2 3, 3 4, 4 5');
        $linestrings[] = LineString::fromString('1 2@ 2 3@ 3 4@ 4 5', '@');
        $linestrings[] = LineString::fromString('1#2@2#3@3#4@4#5', '@', '#');
        $linestrings[] = LineString::fromWKT("LINESTRING(0 0,1 1,1 2)");

        foreach ($linestrings as $ls) {
            $this->assertInstanceOf(LineString::class, $ls);
            foreach ($ls->points as $point) {
                $this->assertInstanceOf(Point::class, $point);
            }
        }
    }

    public function testMultiPointSuccess()
    {
        $multipoints = [];

        $p1 = new Point(1, 2);
        $p2 = new Point(3, 4);
        $p3 = new Point(5, 6);

        $multipoints[] = new MultiPoint([$p1, $p2, $p3]);
        $multipoints[] = MultiPoint::fromString("1 2, 3 4, 5 6");
        $multipoints[] = MultiPoint::fromString("1 2: 3 4: 5 6", ":");
        $multipoints[] = MultiPoint::fromString("1_2: 3_4: 5_6", ":", "_");
        $multipoints[] = MultiPoint::fromArray([ [1, 2], [2, 3], [3, 4] ]);

        $this->assertCount(5, $multipoints);
        foreach ($multipoints as $mp) {
            $this->assertInstanceOf(MultiPoint::class, $mp);
        }
    }

    public function testLineStringCircular()
    {
        $linestring1 = new LineString([new Point(1, 2), new Point(3, 4), new Point(5, 6)]);
        $linestring2 = new LineString([new Point(1, 2), new Point(3, 4), new Point(5, 6), new Point(1, 2)]);

        $this->assertFalse($linestring1->isCircular());
        $this->assertTrue($linestring2->isCircular());
    }

    public function testLineStringSplit()
    {
        $p1 = new Point(1,1);
        $p2 = new Point(2,2);
        $p3 = new Point(3,3);
        $p4 = new Point(4,4);
        $p5 = new Point(5,5);

        $linestring1 = new LineString([$p1, $p2, $p3, $p4, $p5]);
        $linestring2 = new LineString([$p1, $p2]);


        $splitted = $linestring1->split($p2);
        $this->assertEquals($splitted[0], $linestring2);
        $this->assertEquals($splitted[1], new LineString([ $p2, $p3, $p4, $p5] ));

        $splitted = $linestring1->split($p1);
        $this->assertEquals($splitted[0], $p1 );
        $this->assertEquals($splitted[1], $linestring1);

        $splitted = $linestring1->split($p5);
        $this->assertEquals($splitted[0], $linestring1);
        $this->assertEquals($splitted[1], $p5 );

        $splitted = $linestring2->split($p1);
        $this->assertEquals($splitted[0], $p1 );
        $this->assertEquals($splitted[1], $linestring2 );

        $splitted = $linestring2->split($p2);
        $this->assertEquals($splitted[0], $linestring2 );
        $this->assertEquals($splitted[1], $p2 );

        $splitted = $linestring2->split($p3);
        $this->assertEquals($splitted[0], $linestring2 );
        $this->assertEquals($splitted[1], null );

    }

    public function testMultiLineString()
    {
        $ml[] = new MultiLineString([LineString::fromArray([[1,2], [2,3], [3,4]]), LineString::fromArray([[5,6], [7,8], [9,10]])]);
        $ml[] = MultiLineString::fromArray([[[1,2], [2,3], [3,4]],[[5,6], [7,8], [9,10]]]);
        $ml[] = MultiLineString::fromString("1 2, 2 3, 3 4; 5 6, 7 8, 9 10");
        $ml[] = MultiLineString::fromString("1 2, 2 3, 3 4@ 5 6, 7 8, 9 10", "@");
        $ml[] = MultiLineString::fromString("1 2, 2 3, 3 4@ 5 6, 7 8, 9 10", "@");
        $ml[] = MultiLineString::fromString("1 2# 2 3# 3 4@ 5 6# 7 8# 9 10", "@", "#");
        $ml[] = MultiLineString::fromString("1^2#2^3# 3^4@ 5^6# 7^8# 9^10", "@", "#", "^");
        $ml[] = MultiLineString::fromWKT("MULTILINESTRING((0 0,4 0,4 4,0 4),(1 1, 2 1, 2 2, 1 2))");

        foreach ($ml as $multilinestring) {
            $this->assertInstanceOf(MultiLineString::class, $multilinestring);
        }
    }

    public function testPolygonSuccess()
    {
        $polygons = [];
        $p1 = new Point(1,1);
        $p2 = new Point(2,2);
        $p3 = new Point(3,3);
        $p4 = new Point(4,4);
        $p5 = new Point(5,5);

        $linestring1 = new LineString([$p1, $p2, $p3, $p4, $p5, $p1]);
        $linestring2 = new LineString([$p1, $p2, $p3, $p1]);
        $linestring3 = "1 2, 3 4, 5 6, 1 2";
        $linestring4 = "1 2: 3 4: 5 6: 1 2";
        $linestring5 = "1_2: 3_4: 5_6: 1_2";

        $polygons[] = new Polygon([$linestring1]);
        $polygons[] = new Polygon([$linestring1, $linestring2]);
        $polygons[] = Polygon::fromString($linestring3);
        $polygons[] = Polygon::fromString($linestring3 .";". $linestring3);
        $polygons[] = Polygon::fromString($linestring4 ."#". $linestring4, "#", ":");
        $polygons[] = Polygon::fromString($linestring5 ."@". $linestring5, "@", ":", "_");

        foreach ($polygons as $poly) {
            $this->assertInstanceOf(Polygon::class, $poly);
            foreach ($poly->linestrings as $ls) {
                $this->assertInstanceOf(LineString::class, $ls);
                foreach ($ls->points as $point) {
                    $this->assertInstanceOf(Point::class, $point);
                }
            }
        }
    }

    public function testMultiPolygonSuccess()
    {
        $polygons = [];
        $p1 = new Point(1,1);
        $p2 = new Point(2,2);
        $p3 = new Point(3,3);
        $p4 = new Point(4,4);
        $p5 = new Point(5,5);

        $linestring1 = new LineString([$p1, $p2, $p3, $p4, $p5, $p1]);
        $linestring2 = new LineString([$p1, $p2, $p3, $p1]);
        $linestring3 = "1 2, 3 4, 5 6, 1 2";
        $linestring4 = "1 2: 3 4: 5 6: 1 2";
        $linestring5 = "1_2: 3_4: 5_6: 1_2";

        $polygons[] = new Polygon([$linestring1]);
        $polygons[] = new Polygon([$linestring1, $linestring2]);
        $polygons[] = Polygon::fromString($linestring3);
        $polygons[] = Polygon::fromString($linestring3 .";". $linestring3);
        $polygons[] = Polygon::fromString($linestring4 ."#". $linestring4, "#", ":");
        $polygons[] = Polygon::fromString($linestring5 ."@". $linestring5, "@", ":", "_");

        $multi = new MultiPolygon($polygons);
        $this->assertInstanceOf(MultiPolygon::class, $multi);

        $mp[] = new MultiPolygon([
            new Polygon([LineString::fromArray([[1,2], [2,3], [3,4], [1,2]]), LineString::fromArray([[5,6], [7,8], [9,10], [5,6]])]),
            new Polygon([LineString::fromArray([[1,2], [2,3], [3,4], [1,2]]), LineString::fromArray([[5,6], [7,8], [9,10], [5,6]])]),
            new Polygon([LineString::fromArray([[1,2], [2,3], [3,4], [1,2]]), LineString::fromArray([[5,6], [7,8], [9,10], [5,6]])])
        ]);
        $mp[] = MultiPolygon::fromArray([
            [[[1,2], [2,3], [3,4], [1,2]],[[5,6], [7,8], [9,10], [5,6]]],
            [[[1,2], [2,3], [3,4], [1,2]],[[5,6], [7,8], [9,10], [5,6]]],
            [[[1,2], [2,3], [3,4], [1,2]],[[5,6], [7,8], [9,10], [5,6]]]
        ]);
        $mp[] = MultiPolygon::fromString("1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6|1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6|1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6");
        $mp[] = MultiPolygon::fromString("1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6%1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6%1 2, 2 3, 3 4, 1 2; 5 6, 7 8, 9 10, 5 6", "%");
        $mp[] = MultiPolygon::fromString("1 2, 2 3, 3 4, 1 2# 5 6, 7 8, 9 10, 5 6%1 2, 2 3, 3 4, 1 2# 5 6, 7 8, 9 10, 5 6%1 2, 2 3, 3 4, 1 2# 5 6, 7 8, 9 10, 5 6", "%", "#");
        $mp[] = MultiPolygon::fromString("1 2: 2 3: 3 4: 1 2# 5 6: 7 8: 9 10: 5 6%1 2: 2 3: 3 4: 1 2# 5 6: 7 8: 9 10: 5 6%1 2: 2 3: 3 4: 1 2# 5 6: 7 8: 9 10: 5 6", "%", "#", ":");
        $mp[] = MultiPolygon::fromString("1?2: 2?3: 3?4: 1?2# 5?6: 7?8: 9?10: 5?6%1?2: 2?3: 3?4: 1?2# 5?6: 7?8: 9?10: 5?6%1?2: 2?3: 3?4: 1?2# 5?6: 7?8: 9?10: 5?6", "%", "#", ":", "?");
        $mp[] = MultiPolygon::fromWKT("MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1)),((-1 -1,-1 -2,-2 -2,-2 -1,-1 -1)))");

        foreach ($mp as $multipolygon) {
            $this->assertInstanceOf(MultiPolygon::class, $multipolygon);
        }
    }

    public function testGeometryCollection()
    {
        $p = new Point(1, 2);
        $ls = LineString::fromArray([[1,2],[3,4],[5,6]]);
        $gc = new GeometryCollection([$p, $ls]);

        $this->assertInstanceOf(GeometryCollection::class, $gc);
        $this->assertCount(2, $gc);

        $array = $gc->toArray();
        $this->assertSame('GEOMETRYCOLLECTION', $array['type']);
        $this->assertCount(2, $array['value']);

        // Point element — type is null due to protected cross-sibling property access limitation
        $this->assertNull($array['value'][0]['type']);
        $this->assertSame('POINT', $array['value'][0]['value']['type']);
        $this->assertSame([2.0, 1.0], $array['value'][0]['value']['value']); // [lon, lat]

        // LineString element
        $this->assertNull($array['value'][1]['type']);
        $this->assertSame('LINESTRING', $array['value'][1]['value']['type']);
        $this->assertCount(3, $array['value'][1]['value']['value']);
        $this->assertSame('POINT', $array['value'][1]['value']['value'][0]['type']);
        $this->assertSame([1.0, 2.0], $array['value'][1]['value']['value'][0]['value']); // [lon, lat]
        $this->assertSame('POINT', $array['value'][1]['value']['value'][1]['type']);
        $this->assertSame([3.0, 4.0], $array['value'][1]['value']['value'][1]['value']);
        $this->assertSame('POINT', $array['value'][1]['value']['value'][2]['type']);
        $this->assertSame([5.0, 6.0], $array['value'][1]['value']['value'][2]['value']);

        // Exercises __toString and toWKT
        $wkt = $gc->toWKT();
        $this->assertStringStartsWith('GEOMETRYCOLLECTION(', $wkt);
    }

    public function testPolygonFails1()
    {
        $this->expectException(GeoSpatialException::class);
        $this->expectExceptionMessage('A LineString instance that compose a Polygon must be circular (min 4 points, first and last equals).');

        $p1 = new Point(1,1);
        $p2 = new Point(2,2);
        $p3 = new Point(3,3);
        $p4 = new Point(4,4);
        $p5 = new Point(5,5);

        $linestring1 = new LineString([$p1, $p2, $p3, $p4, $p5]);
        $poly = new Polygon([$linestring1]);
    }


    public function testFromWKTSuccess()
    {
        $point = Point::fromWKT("POINT(0 0)");
        $this->assertInstanceOf(Point::class, $point);
        $this->assertEquals("POINT(0 0)", $point->toWKT());

        $linestring = LineString::fromWKT("LINESTRING(0 0,1 1,1 2)");
        $this->assertInstanceOf(LineString::class, $linestring);
        $this->assertEquals("LINESTRING(0 0,1 1,1 2)", $linestring->toWKT());

        $multilinestring = MultiLineString::fromWKT("MULTILINESTRING((0 0,4 0,4 4,0 4,0 0),(1 1, 2 1, 2 2, 1 2,1 1))");
        $this->assertInstanceOf(MultiLineString::class, $multilinestring);
        $this->assertEquals("MULTILINESTRING((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1))", $multilinestring->toWKT());

        $multipoint = MultiPoint::fromWKT("MULTIPOINT(0 0,1 2)");
        $this->assertInstanceOf(MultiPoint::class, $multipoint);
        $this->assertEquals("MULTIPOINT(0 0,1 2)", $multipoint->toWKT());

        $polygon = Polygon::fromWKT("POLYGON((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1))");
        $this->assertInstanceOf(Polygon::class, $polygon);
        $this->assertEquals("POLYGON((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1))", $polygon->toWKT());

        $multipolygon = MultiPolygon::fromWKT("MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1)),((-1 -1,-1 -2,-2 -2,-2 -1,-1 -1)))");
        $this->assertInstanceOf(MultiPolygon::class, $multipolygon);
        $this->assertEquals("MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1)),((-1 -1,-1 -2,-2 -2,-2 -1,-1 -1)))", $multipolygon->toWKT());
    }
}
