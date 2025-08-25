# 🚍 Trip & Booking API Testing Guide

## 1. Create a Trip

### Endpoint
```
POST /trips/manage.php
```

### Request Body (JSON)
| Field         | Type    | Required | Description                                 |
|---------------|---------|----------|---------------------------------------------|
| company_id    | int     | Yes      | Company ID                                  |
| vehicle_id    | int     | Yes      | Vehicle ID                                  |
| route_id      | int     | Yes      | Route ID                                    |
| departure_time| string  | Yes      | Format: `YYYY-MM-DD HH:MM:SS`               |
| created_by    | int     | Yes      | User ID creating the trip                   |
| driver_id     | int     | Yes      | Driver’s user ID                            |
| conductor_id  | int     | Yes      | Conductor’s user ID                         |
| device_uuid   | string  | Yes      | Device identifier                           |
| is_express    | int     | Yes      | 1 = Express, 0 = Non-express                |

#### Example: Express Trip
```json
{
  "company_id": 1,
  "vehicle_id": 21,
  "route_id": 5,
  "departure_time": "2025-08-01 08:00:00",
  "created_by": 26,
  "driver_id": 123,
  "conductor_id": 456,
  "device_uuid": "test-device-001",
  "is_express": 1
}
```

#### Example: Non-Express Trip
```json
{
  "company_id": 1,
  "vehicle_id": 21,
  "route_id": 5,
  "departure_time": "2025-08-01 10:00:00",
  "created_by": 26,
  "driver_id": 123,
  "conductor_id": 456,
  "device_uuid": "test-device-002",
  "is_express": 0
}
```

### Success Response
```json
{
  "success": true,
  "message": "Trip created successfully",
  "vehicle": "KFC 433Y",
  "device_id": "test-device-001",
  "device_name": "Test Device 1",
  "trip": {
    "id": 201,
    "trip_code": "NAT-NAI-20250720-8",
    "status": "ongoing",
    "plate_number": "KFC 433Y"
  }
}
```

---

## 2. Book a Seat

### Endpoint
```
POST /bookings/manage.php
```

### A. Express Trip Booking (`is_express = 1`)

#### Required Fields
- `trip_id`
- `seat_number`
- `device_uuid`
- Either `fare_amount` or `destination_id` (to fetch fare from destination)

#### Examples

**1. With Fare Provided**
```json
{
  "trip_id": 201,
  "seat_number": "1A",
  "fare_amount": 1000,
  "device_uuid": "test-device-001"
}
```

**2. With Destination Provided (no fare)**
```json
{
  "trip_id": 201,
  "seat_number": "1B",
  "destination_id": 3,
  "device_uuid": "test-device-001"
}
```

**3. Error: Neither Fare nor Destination**
```json
{
  "trip_id": 201,
  "seat_number": "1C",
  "device_uuid": "test-device-001"
}
```
**Response:**
```json
{
  "error": true,
  "message": "fare_amount or destination_id is required for express trips."
}
```

**4. Error: Different Fare**
- If a booking is made with a different fare than previous bookings for the same trip:
```json
{
  "error": true,
  "message": "All fares for an express trip must be the same. Use 1000"
}
```

---

### B. Non-Express Trip Booking (`is_express = 0`)

#### Required Fields
- `trip_id`
- `customer_name`
- `customer_phone`
- `destination_id`
- `seat_number`
- `device_uuid`
- `fare_amount` (optional; if omitted, will be fetched from destination)

#### Examples

**1. With Fare Provided**
```json
{
  "trip_id": 202,
  "customer_name": "John Doe",
  "customer_phone": "0712345678",
  "destination_id": 3,
  "seat_number": "2A",
  "fare_amount": 1350,
  "device_uuid": "test-device-002"
}
```

**2. Without Fare (uses destination fare)**
```json
{
  "trip_id": 202,
  "customer_name": "Jane Doe",
  "customer_phone": "0798765432",
  "destination_id": 3,
  "seat_number": "2B",
  "device_uuid": "test-device-002"
}
```

**3. Error: No Fare Found**
```json
{
  "error": true,
  "message": "No fare found for this destination."
}
```

---

## 3. Validation Rules

- **Express Trips:**
  - All bookings must have the same fare.
  - If `fare_amount` is not provided, and `destination_id` is, fare is fetched from destination.
  - If neither is provided, booking fails.
  - `customer_name` and `customer_phone` are not required.

- **Non-Express Trips:**
  - If `fare_amount` is not provided, fare is fetched from destination.
  - If destination has no fare, booking fails.
  - `customer_name` and `customer_phone` are required.

---

## 4. Response Example (Booking Success)
```json
{
  "success": true,
  "message": "Booking Confirmed: Seat 1A on Nairobi - Eldoret to Kericho, Departure: 2025-07-16 08:15:27, Vehicle: KFC 433Y (Mini Bus). Fare: KES 1350.00. Safe journey!",
  "device_id": "test-device-001",
  "device_name": "Test Device 1",
  "office_name": "Nairobi Office",
  "booking": { ... },
  "receipt_number": "TKT-201-1",
  "sms_result": { ... }
}
```

---

## 5. Testing Checklist

- [ ] Can create both express and non-express trips.
- [ ] Can book seats on express trips with fare or destination.
- [ ] Can book seats on non-express trips with or without fare.
- [ ] Error messages are clear for missing/invalid data.
- [ ] All fares for express trip are enforced to be the same.
