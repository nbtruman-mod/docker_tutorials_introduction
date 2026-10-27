# Docker Tutorial
## Part 2 — Adding MySQL and Understanding Data Persistence

### 1. Introduction

In Part 1, we created a simple PHP application and ran it inside a Docker container.

In this part, we will extend that application by introducing a MySQL database in a second container.

Our application will allow users to enter messages and save them to the database. When the page is refreshed, the saved messages will be retrieved and displayed.

Along the way, we will explore how containers communicate and, most importantly, how Docker handles data persistence.

By the end of this tutorial, you will understand how to:

- Run multiple containers as part of one application.
- Configure a MySQL database using Docker Compose.
- Connect to MySQL from PHP using PDO.
- Initialise a database and create a table.
- Store and retrieve data from the database.
- Stop and restart containers without losing their data.
- Understand why removing a database container presents a different problem from stopping it.
- Recognise when a Docker volume is needed.

We will use PHP 8.3 with Apache and MySQL 8.

### 2. Review the existing project

We will continue working with the project created in Part 1.

The directory structure should currently look like this:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
└── compose.yml
```

Our PHP application runs in the `app` container.

We will introduce a second container, called `database`, to run MySQL.

The two containers will communicate over a Docker network managed by Docker Compose.

Our architecture will look like this:

```text
                  Web browser
                       |
                       | HTTP
                       v
                +--------------+
                |     app      |
                |              |
                | Apache + PHP |
                +--------------+
                       |
                       | MySQL connection
                       v
                +--------------+
                |   database   |
                |              |
                |    MySQL     |
                +--------------+
```

The browser communicates with the PHP application. The PHP application communicates with MySQL.

The browser does not need to connect directly to the database.

This separation is useful because each container has a distinct responsibility. We can configure, update and manage the application and database independently.

### 3. Update the PHP Dockerfile

Before PHP can communicate with MySQL, we need to ensure that the appropriate PHP extensions are installed.

Open `app/Dockerfile`.

It currently contains:

```dockerfile
FROM php:8.3-apache
```

Replace it with:

```dockerfile
FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql
```

Let's examine the additional instruction.

#### PDO

PDO, or PHP Data Objects, provides PHP with a consistent interface for working with databases.

It allows us to prepare SQL statements, execute queries and retrieve results without having to write all the database interaction code ourselves.

#### PDO MySQL

The `pdo_mysql` extension allows PDO to communicate with MySQL databases.

The PHP image already includes the necessary build tools to compile this extension using `docker-php-ext-install`.

We have added the instruction to our Dockerfile, but it will not take effect in the existing image until we rebuild it.

We will do that after updating the Compose configuration.

### 4. Add the MySQL service

Open `compose.yml`.

It should currently contain:

```yaml
services:
  app:
    build: ./app
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
```

Replace it with:

```yaml
services:
  app:
    build: ./app
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
    environment:
      DB_HOST: database
      DB_NAME: php_app
      DB_USER: php_user
      DB_PASSWORD: development_password
    depends_on:
      - database

  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
```

We have introduced a second service, `database`.

Let's examine the new configuration.

#### The database image

```yaml
database:
  image: mysql:8.0
```

Instead of building our own database image, we are using the existing `mysql:8.0` image.

This image provides a MySQL database server.

Docker will download it from the image registry if it is not already available locally.

Notice the difference between our two services:

- The `app` service uses `build` because we have a Dockerfile that defines our PHP environment.
- The `database` service uses `image` because we can use an existing MySQL image directly.

We do not need to write a Dockerfile for the database.

#### Database configuration

```yaml
environment:
  MYSQL_DATABASE: php_app
  MYSQL_USER: php_user
  MYSQL_PASSWORD: development_password
  MYSQL_ROOT_PASSWORD: development_root_password
```

These environment variables configure the MySQL image.

When MySQL initialises a new database data directory, it uses these settings to create:

- A database named `php_app`.
- A database user named `php_user`.
- A password for that user.
- A password for the MySQL root account.

The application will use the `php_user` account rather than the root account.

This is good practice because an application should generally use an account with only the privileges it needs.

These credentials are deliberately simple examples for a local tutorial. They must not be reused for a production application.

One important detail: these initialisation variables generally apply when MySQL first initialises an empty data directory. Changing an environment variable later does not automatically change the password of an existing database user.

#### Configuring the PHP application

We have also added these environment variables to the `app` service:

```yaml
environment:
  DB_HOST: database
  DB_NAME: php_app
  DB_USER: php_user
  DB_PASSWORD: development_password
```

These provide the connection details our PHP application will use.

Notice that `DB_HOST` is set to `database`.

This is the name of our Compose service, not the name of the database image or the container's IP address.

Docker Compose creates a network for the application and allows services to discover one another by service name.

Consequently, our PHP application can connect to MySQL using the hostname `database`.

We do not need to discover or hard-code the database container's IP address.

Also notice that we have not published MySQL's port to the host machine. The PHP application can reach MySQL over the internal Docker network without exposing the database port to our computer.

#### The `depends_on` instruction

```yaml
depends_on:
  - database
```

This tells Compose that the database service is a dependency of the application service.

Compose will start the database service before starting the application service.

However, this does not guarantee that MySQL is ready to accept connections when PHP first starts. Starting a container and having its application ready to use are two different things.

For this simple tutorial, we will start the services and check the logs if a connection fails during initialisation.

For a more robust application, we would normally add a MySQL health check and appropriate connection retry logic.

### 5. Create the database table

We now have a MySQL server, but we need a table in which to store our messages.

We could create the table manually by connecting to MySQL, but Docker provides a convenient way to initialise a new database.

The official MySQL image processes supported initialisation scripts placed in `/docker-entrypoint-initdb.d/` when it initialises an empty data directory.

We will use that mechanism to create our table.

Create a new directory named `database` in the project root, then create `init.sql` inside it.

Our project will now look like this:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
├── database/
│   └── init.sql
└── compose.yml
```

Add the following SQL to `database/init.sql`:

```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

This creates a table called `messages`.

The table has three columns:

| Column | Purpose |
|---|---|
| `id` | A unique identifier for each message. MySQL generates this automatically. |
| `message` | Stores the text entered by the user. |
| `created_at` | Records when the message was created. |

We now need to make this script available to the database container.

Update the `database` service in `compose.yml`:

```yaml
  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
    volumes:
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
```

The `volumes` instruction here is a **bind mount**, not the persistent named volume we will introduce later.

It makes the local SQL file available inside the container at the path expected by the MySQL image.

The `:ro` suffix means that the file is mounted read-only inside the container.

This mount provides an initialisation script. It does not store the database's records.

That distinction will become important when we investigate data persistence.

Also remember that MySQL runs its initialisation scripts when it first initialises an empty data directory. If a database already exists, simply changing `init.sql` will not automatically rerun the script.

### 6. Update the PHP application

Our PHP application currently displays a static message.

We will replace it with a simple form that allows users to submit messages and view previously saved messages.

Open `app/index.php` and replace its contents with:

```php
<?php

$host = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

$dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Unable to connect to the database. Please try again shortly.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message !== '') {
        $statement = $pdo->prepare(
            'INSERT INTO messages (message) VALUES (:message)'
        );

        $statement->execute([
            'message' => $message,
        ]);
    }

    header('Location: /');
    exit;
}

$statement = $pdo->query(
    'SELECT id, message, created_at FROM messages ORDER BY id DESC'
);

$messages = $statement->fetchAll();

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Docker Tutorial</title>
</head>
<body>

<h1>PHP Docker Tutorial</h1>

<h2>Leave a message</h2>

<form method="post">
    <label for="message">Message:</label>
    <input type="text" id="message" name="message" required>
    <button type="submit">Save message</button>
</form>

<h2>Saved messages</h2>

<?php if (count($messages) === 0): ?>

    <p>No messages have been saved yet.</p>

<?php else: ?>

    <ul>
        <?php foreach ($messages as $message): ?>
            <li>
                <?= escape($message['message']) ?>
                <small>
                    (<?= escape($message['created_at']) ?>)
                </small>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>

</body>
</html>
```

Let's examine the important parts.

#### Connecting to MySQL

We retrieve the connection details from the environment:

```php
$host = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
```

These values come from the `environment` section of our Compose configuration.

We then construct a PDO connection:

```php
$dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
```

The hostname is `database`, because that is the name of our database service.

The connection uses the database and application user we configured earlier.

We enable exception-based error handling so that database errors can be handled explicitly.

If MySQL is not yet available, the application returns an HTTP 503 response rather than displaying internal connection details to the user.

This is a simple starting point. A production application would normally log the underlying exception privately and implement a suitable retry strategy.

#### Saving a message

When a user submits the form, PHP receives the message through the POST request.

We use a prepared statement to insert it into MySQL:

```php
$statement = $pdo->prepare(
    'INSERT INTO messages (message) VALUES (:message)'
);

$statement->execute([
    'message' => $message,
]);
```

The prepared statement separates the SQL structure from the submitted value, helping protect the database query against SQL injection.

After inserting the message, we redirect the browser to the main page.

This prevents a browser refresh from immediately resubmitting the same form.

#### Retrieving messages

We retrieve the saved records using:

```php
$statement = $pdo->query(
    'SELECT id, message, created_at FROM messages ORDER BY id DESC'
);

$messages = $statement->fetchAll();
```

The application then displays the results in the HTML page.

We also escape message content before displaying it. This is good practice whenever we render user-submitted text in HTML.

### 7. Build and start the application

We have now changed the Dockerfile, updated the Compose configuration, added an SQL initialisation script and modified the PHP application.

From the project root, run:

```bash
docker compose up --build -d
```

Docker Compose will:

1. Build the PHP application image, including the PDO MySQL extension.
2. Download the MySQL image if necessary.
3. Create the containers and network.
4. Start the database and application services.
5. Make the initialisation script available to MySQL.

Check the container status:

```bash
docker compose ps
```

Both services should be running.

Now check the database logs:

```bash
docker compose logs database
```

MySQL may take a little time to initialise on its first run.

Look for messages indicating that the server is ready to accept connections.

If the application fails to connect immediately, check its logs:

```bash
docker compose logs app
```

You can also restart the application service after the database is ready:

```bash
docker compose restart app
```

Now open your browser and visit:

`http://localhost:8080`

You should see the message form.

Enter a message and submit it.

The page should reload, displaying the message you entered.

Submit another message.

Both messages should appear in the list.

We now have a working PHP application that stores data in a separate MySQL container.

### 8. Confirm that the database is storing data

Before investigating persistence, let's confirm that our records really are stored in MySQL rather than just being held in PHP memory.

Open a terminal in the project directory and run:

```bash
docker compose exec database mysql -u php_user -p php_app
```

When prompted, enter:

```text
development_password
```

You should now be connected to MySQL.

Run the following SQL query:

```sql
SELECT * FROM messages;
```

You should see the messages you submitted through the application.

This confirms that the data is stored in the database.

To leave the MySQL client, run:

```sql
exit
```

We can now investigate what happens to those records when we stop and restart the containers.

### 9. Stop and restart the containers

This is our first persistence test.

We have not configured a persistent database volume. The database is currently using the writable filesystem associated with its container.

Let's see what happens when we stop the application.

First, stop the containers:

```bash
docker compose stop
```

This stops the containers without removing them.

Now start them again:

```bash
docker compose start
```

Open the application at `http://localhost:8080`.

Your previously submitted messages should still be present.

Why?

**Stopping a container does not remove its filesystem.**

The MySQL database files remain in the container's writable layer while the container exists. When we start that same container again, MySQL can access its existing database files.

This is an important distinction: a stopped container retains its writable layer.

The same principle applies to other files written into a container's writable filesystem. They generally remain available when that container is stopped and restarted.

Let's verify this once more by adding another message, stopping the application, and starting it again.

The new message should also still be present.

At this point, we have demonstrated that stopping a container does not automatically erase its data.

However, this does not mean that our database storage is configured as we would want for a durable application.

### 10. Remove and recreate the containers

Now let's investigate what happens when we remove the containers rather than merely stopping them.

**Warning: this exercise deliberately demonstrates data loss.** We have not configured a persistent database volume, so removing the database container will discard the database files stored in its writable layer.

Make sure you are working with this tutorial's database and do not use these commands against a database containing anything important.

First, stop and remove the Compose containers:

```bash
docker compose down
```

Unlike `docker compose stop`, this removes the containers.

Our application source files remain on the host computer, but the MySQL container's writable layer is removed along with the container.

Now recreate the application:

```bash
docker compose up --build -d
```

Docker Compose creates new containers.

The new MySQL container initialises its database again, including creating the `php_app` database and executing our `init.sql` script.

Visit:

`http://localhost:8080`

The application should work, but your previous messages will be gone.

The table exists, but it contains no records.

Why?

The new database container has a new writable layer. It does not inherit the old container's database files.

This is the fundamental difference between stopping a container and removing it.

| Operation | Container retained? | Data in its writable layer |
|---|---|---|
| Stop the container | Yes | Retained |
| Start the stopped container | Yes | Retained |
| Remove the container | No | Lost with the container |
| Create a replacement container | A new container is created | Old writable-layer data is not recovered |

There are other ways to retain data, such as copying files out of a container, but relying on the container's writable layer is not a good way to manage important database data.

We need a way to store database files independently of the database container.

That brings us to Docker volumes.

### 11. Why database persistence matters

Containers are designed to be replaceable.

We might need to recreate a container because:

- We have updated the database image.
- We have changed the container configuration.
- We are rebuilding the application environment.
- We are recovering from a problem.
- We are deploying a replacement container.

For an application container, replacing the container is usually straightforward when the application source code is stored on the host or packaged into the image.

A database is different because it contains data that accumulates over time.

If we store all our database files only in the container's writable layer, removing the container removes those files.

We therefore want to separate the lifetime of the database data from the lifetime of the container running MySQL.

Docker provides a mechanism for doing this: **volumes**.

A Docker volume is managed separately from the container. We can attach it to a database container so that MySQL stores its data there.

If we subsequently remove and recreate the container while retaining the volume, the new container can use the existing database files.

This is the approach we will implement in Part 3.

### 12. Checkpoint

Before moving on, make sure you can answer these questions.

1. Why does our application need a second container?
2. Why can PHP connect to MySQL using `database` as the hostname?
3. What does the `pdo_mysql` extension provide?
4. What is the purpose of the `MYSQL_DATABASE` environment variable?
5. What does `depends_on` do, and what does it not guarantee?
6. Where are the database files stored when we have not configured a volume?
7. Why do our messages survive stopping and restarting the database container?
8. Why are the messages lost when we remove and recreate the database container?
9. What is the difference between a container's writable layer and a Docker volume?
10. Why might we want database data to outlive the container running MySQL?

Try answering these questions before proceeding to the next part.

### 13. Summary

In this part, we extended our PHP application to use a MySQL database running in a separate Docker container.

We configured the database through environment variables, created a table using an initialisation script, and used PHP's PDO extension to store and retrieve messages.

We then tested the application's data persistence.

We demonstrated that stopping and restarting a container preserves its writable filesystem. However, removing that container discards data stored only in its writable layer.

This is why we need to distinguish between the lifecycle of a container and the lifecycle of the data it manages.

In **Part 3**, we will introduce a Docker-managed named volume and configure MySQL to store its database files there. We will then repeat our experiment, removing and recreating the database container while retaining the volume, to demonstrate how persistent storage solves the problem.