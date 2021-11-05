<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello GiHub!");
    return $response;
});

$app->post('/order/add', function (Request $request, Response $response) {
    try {
        $maxOrderId = 0;
        $orderTotal = 0;
        $data = $request->getParsedBody();
        if(empty($data['customerId']) || empty($data['items'])) {
            throw new Exception('Unexpected payload!');
        }
        //get json orders
        $orderJsonData = file_get_contents('data/orders.json');
        $orderJsonData = json_decode($orderJsonData, true);
        //get json products
        $productJsonData = file_get_contents('data/products.json');
        $productJsonData = json_decode($productJsonData, true);
        //get json customers
        $customerJsonData = file_get_contents('data/customers.json');
        $customerJsonData = json_decode($customerJsonData, true);

        foreach ($data['items'] as &$item) {
            foreach ($productJsonData as &$product) {
                if ($item['productId'] == $product['id']) {
                    if (intval($product['stock']) < intval($item['quantity'])) {
                        throw new Exception('Insufficient stock for ' . $product['name'] . '! Available stock: ' . $product['stock']);
                    }
                    $product['stock'] = intval($product['stock']) - intval($item['quantity']);
                    $item['unitPrice'] = $product['price'];
                    $item['total'] = number_format((floatval($product['price']) * intval($item['quantity'])), 2, '.', '');
                    $orderTotal += (floatval($product['price']) * intval($item['quantity']));
                    break;
                }
            }
        }

        foreach ($orderJsonData as $order) {
            if (intval($order['id']) > $maxOrderId) {
                $maxOrderId = intval($order['id']);
            }
        }
        $data = array('id' => $maxOrderId + 1) + $data;
        $data['total'] = number_format($orderTotal, 2, '.', '');
        array_push($orderJsonData, $data);

        //update product json file
        $productJsonData = json_encode($productJsonData, JSON_PRETTY_PRINT);
        file_put_contents('data/products.json', $productJsonData);

        //put new order to json orders file
        $orderJsonData = json_encode($orderJsonData, JSON_PRETTY_PRINT);
        file_put_contents('data/orders.json', $orderJsonData);

        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'status' => 'success',
                        'order' => $data
                    )
                )
            );
    } catch(\Exception $e) {
        $response = $response->withStatus(422);
        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'errorMessage' => $e->getMessage()
                    )
                )
            );
    } finally {
        return $response
            ->withHeader('Content-Type', 'application/json');
    }
});

$app->put('/order/edit/{orderid}', function (Request $request, Response $response, $args) {
    // Sipariş düzenleme işleminde siparişteki adetler azaltılırsa products.json dosyasındaki stok adetini arttırmak için bir kod yazmadım.
    try {
        $data = $request->getParsedBody();
        $orderid = $args['orderid'];
        $orderTotal = 0;
        if(empty($data['items']) || empty($orderid)) {
            throw new Exception('Unexpected payload!');
        }

        //get json orders
        $orderJsonData = file_get_contents('data/orders.json');
        $orderJsonData = json_decode($orderJsonData, true);
        //get json products
        $productJsonData = file_get_contents('data/products.json');
        $productJsonData = json_decode($productJsonData, true);
        //get json customers
        $customerJsonData = file_get_contents('data/customers.json');
        $customerJsonData = json_decode($customerJsonData, true);

        foreach ($data['items'] as &$item) {
            foreach ($productJsonData as &$product) {
                if ($item['productId'] == $product['id']) {
                    if (intval($product['stock']) < intval($item['quantity'])) {
                        throw new Exception('Insufficient stock for ' . $product['name'] . '! Available stock: ' . $product['stock']);
                    }
                    $product['stock'] = intval($product['stock']) - intval($item['quantity']);
                    $item['unitPrice'] = $product['price'];
                    $item['total'] = number_format((floatval($product['price']) * intval($item['quantity'])), 2, '.', '');
                    $orderTotal += (floatval($product['price']) * intval($item['quantity']));
                    break;
                }
            }
        }
        
        $data = array('id' => intval($orderid)) + $data;
        $data['total'] = number_format($orderTotal, 2, '.', '');
        
        foreach ($orderJsonData as &$order) {
            if (intval($order['id']) == intval($orderid)) {
                $order = $data;
            }
        }

        //update product json file
        $productJsonData = json_encode($productJsonData, JSON_PRETTY_PRINT);
        file_put_contents('data/products.json', $productJsonData);

        //put edited order to json orders file
        $orderJsonData = json_encode($orderJsonData, JSON_PRETTY_PRINT);
        file_put_contents('data/orders.json', $orderJsonData);

        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'status' => 'success',
                        'order' => $data
                    )
                )
            );
    } catch(\Exception $e) {
        $response = $response->withStatus(422);
        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'errorMessage' => $e->getMessage()
                    )
                )
            );
    } finally {
        return $response
            ->withHeader('Content-Type', 'application/json');
    }
});

$app->delete('/order/delete/{orderid}', function (Request $request, Response $response, $args) {
    // Sipariş silme işleminde products.json dosyasındaki stok adetini silinen adetler kadar arttırmak için bir kod yazmadım.
    try {
        $orderid = $args['orderid'];
        $found = false;
        if(empty($orderid)) {
            throw new Exception('Unexpected payload!');
        }

        //get json orders
        $orderJsonData = file_get_contents('data/orders.json');
        $orderJsonData = json_decode($orderJsonData, true);

        foreach ($orderJsonData as $key => $order) {
            if (intval($order['id']) == intval($orderid)) {
                unset($orderJsonData[$key]);
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception('Order not found!');
        }
        //revert order reordering after the unset
        $orderJsonData = array_values($orderJsonData);

        //update json orders file
        $orderJsonData = json_encode($orderJsonData, JSON_PRETTY_PRINT);
        file_put_contents('data/orders.json', $orderJsonData);

        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'status' => 'success',
                        'orderid' => $orderid
                    )
                )
            );
    } catch(\Exception $e) {
        $response = $response->withStatus(422);
        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'errorMessage' => $e->getMessage()
                    )
                )
            );
    } finally {
        return $response
            ->withHeader('Content-Type', 'application/json');
    }
});

$app->post('/order/discounts/{orderid}', function (Request $request, Response $response, $args) {
    try {
        $data = $args;
        $products = array();
        $totalDiscount = 0;
        $lowestItemPrice = 999999;
        if(empty($data['orderid'])) {
            throw new Exception('Girilen bilgiler boş olamaz!');
        }

        //get json orders
        $orderJsonData = file_get_contents('data/orders.json');
        $orderJsonData = json_decode($orderJsonData, true);

        //get json products
        $productJsonData = file_get_contents('data/products.json');
        $productJsonData = json_decode($productJsonData, true);

        foreach ($orderJsonData as $key => $order) {
            if (intval($order['id']) == intval($data['orderid'])) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            throw new Exception('Order not found!');
        }

        $discount = array('orderid' => $order['id'], 'discounts' => array(), 'totalDiscount' => '0', 'discountedTotal' => '0');
        $mainPrice = $order['total'];

        foreach ($productJsonData as $product) {
            $products[$product['id']] = array (
                'category' => intval($product['category']),
                'price' => floatval($product['price']),
            );
        }

        foreach ($order['items'] as &$item) {
            if ($lowestItemPrice > $item['unitPrice']) {
                $lowestItemPrice = $item['unitPrice'];
            }
        }

        foreach ($order['items'] as &$item) {
            if ($products[$item['productId']]['category'] == 2 && $item['quantity'] == 6) {
                $totalDiscount += $products[$item['productId']]['price'];
                array_push($discount['discounts'], array(
                    'discountReason' => 'BUY_5_GET_1',
                    'discountAmount' => number_format($products[$item['productId']]['price'], 2, '.', ''),
                    'subtotal' => number_format((floatval($mainPrice) - $products[$item['productId']]['price']), 2, '.', '')
                ));
                $order['total'] = floatval($order['total']) - $products[$item['productId']]['price'];
            }
            if ($products[$item['productId']]['category'] == 1 && $item['quantity'] >= 2) {
                $totalDiscount += $lowestItemPrice;
                array_push($discount['discounts'], array(
                    'discountReason' => '20_PERCENT_MORE_2',
                    'discountAmount' => number_format($totalDiscount, 2, '.', ''),
                    'subtotal' => number_format((floatval($mainPrice) - $lowestItemPrice), 2, '.', '')
                ));
                $order['total'] = floatval($order['total']) - $lowestItemPrice;
            }
        }
        if ($order['total'] > 1000) {
            $totalDiscount += ($mainPrice * 0.1);
            array_push($discount['discounts'], array(
                'discountReason' => '10_PERCENT_OVER_1000',
                'discountAmount' => number_format(($mainPrice * 0.1), 2, '.', ''),
                'subtotal' => number_format((floatval($order['total']) - floatval($mainPrice * 0.1)), 2, '.', '')
            ));
        }
        $discount['totalDiscount'] = number_format($totalDiscount, 2, '.', '');
        $discount['discountedTotal'] = number_format($mainPrice - $totalDiscount , 2, '.', '');

        $response
            ->getBody()
            ->write(
                json_encode(
                    $discount
                )
            );
    } catch(\Exception $e) {
        $response = $response->withStatus(422);
        $response
            ->getBody()
            ->write(
                json_encode(
                    array(
                        'errorMessage' => $e->getMessage()
                    )
                )
            );
    } finally {
        return $response
            ->withHeader('Content-Type', 'application/json');
    }
});

$app->run();
