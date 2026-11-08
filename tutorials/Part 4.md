# Docker Tutorial — Part 4: Sharing a Database Between Independent Compose Projects

## Introduction

In Part 3, we introduced a named Docker volume to ensure that our MySQL database survives the removal and recreation of its container.

Our project now consists of a PHP application and a MySQL database, both managed by a single Docker Compose project.

This works well for a single application, but imagine we want to test several applications against the same database server.

We could put all the applications into one Compose file, but that would couple their lifecycles together. We want to be able to start, stop, and remove an application's containers without affecting the database or other applications.

In this tutorial, we'll separate the database into its own Compose project.

By the end, we'll have:

- A database managed independently from our application.
- A shared Docker network through which applications can reach the database.
- A database that retains its existing data and persistent volume.
- An application that can be stopped, removed, and recreated without affecting the database.
- A foundation for connecting multiple applications to the same database server.

We'll continue using the PHP application and MySQL database from Parts 1–3.

---

## 1. Understand the architecture

Previously, our project looked like this:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
├── database/
│   └── init.sql
└── compose.yml
```

The Compose file defined both services:

```yaml
services:
  app:
    # PHP application configuration

  database:
    # MySQL configuration
```

Compose created a network for the project, allowing the application to connect to MySQL using the hostname `database`.

Both services belonged to the same Compose project.

We want to change the architecture to this:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
├── database/
│   ├── compose.yml
│   └── init.sql
└── compose.yml
```

The root `compose.yml` will manage the application, while `database/compose.yml` will manage MySQL.

Conceptually, the result will be:

```text
Application Compose project
┌─────────────────────────┐
│                         │
│  PHP application        │
│                         │
└────────────┬────────────┘
             │
             │
═════════════╪════════════════ Shared Docker network
             │
             │
┌────────────┴────────────┐
│                         │
│  MySQL database         │
│                         │
└────────────┬────────────┘
             │
             │
      Named Docker volume
             │
             ▼
       Persistent data
```

The application and database will have independent lifecycles, but they will still be able to communicate over the shared network.

Before making any changes, we need to understand two things: Docker networks and Docker volumes.

---

## 2. Understand the difference between Compose projects, networks, and volumes

Docker Compose provides a convenient way to group and manage related containers.

By default, each Compose project gets its own network. Containers in that project can discover one another using their service names.

For example, our PHP application can connect to the MySQL service using:

```text
database
```

That works because both services belong to the same Compose project and share its network.

If we split the services into two projects, each project will normally have its own network. The application will no longer automatically be able to resolve the database service by its name.

We therefore need to create a network that both projects can use.

### Networks

A Docker network provides communication between containers.

We'll create a network called:

```text
app-testing
```

Both Compose projects will join this network.

The database will have the network alias `database`, allowing the application to continue using the same hostname as before.

This is important because we don't want to change our PHP application simply because we've changed how its containers are organised.

### Volumes

A Docker volume provides persistent storage.

In Part 3, we added this configuration:

```yaml
volumes:
  db_data:
```

And mounted the volume into MySQL:

```yaml
volumes:
  - db_data:/var/lib/mysql
```

The volume contains MySQL's persistent database files.

Importantly, Compose normally prefixes the volume's actual Docker name with the project name. If our project is called `php-docker-tutorial`, the actual volume name will typically be:

```text
php-docker-tutorial_db_data
```

The exact name depends on the project name and any explicit volume configuration.

**We must reuse the existing volume, not create a new one.** Otherwise, the new database container would start with an empty data directory and our existing data would appear to have disappeared.

We'll explicitly reference the existing volume when we create the new database project.

---

## 3. Prepare the existing project

Before changing the configuration, let's establish the state of our existing environment.

From the root of `php-docker-tutorial`, run:

```bash
docker compose ps
```

Check that the application and database are running.

If you haven't already done so, add a few messages through the web application at:

```text
http://localhost:8080
```

We'll use these messages to verify that our data survives the changes.

Now list the Docker volumes:

```bash
docker volume ls
```

Look for the volume created in Part 3. With the default project name, it should be called:

```text
php-docker-tutorial_db_data
```

You can inspect it directly:

```bash
docker volume inspect php-docker-tutorial_db_data
```

If your volume has a different name, use that actual name throughout the rest of the tutorial.

**Do not run `docker compose down -v`.** The `-v` option removes the project's declared named volumes, which would destroy the persistent database storage we're trying to preserve.

### Stop the existing project

We'll stop the application and database before changing their configuration:

```bash
docker compose down
```

This removes the containers and the default Compose network, but retains the named database volume.

The database files remain on the host in Docker-managed storage, ready to be used by a new container.

Verify that the volume still exists:

```bash
docker volume ls
```

At this point, the original database container is gone, but its data is still available.

---

## 4. Create a shared Docker network

We want both Compose projects to use the same network.

Create it manually:

```bash
docker network create app-testing
```

Docker will return the network's identifier.

You can inspect the network with:

```bash
docker network inspect app-testing
```

Initially, there should be no application or database containers attached to it.

### Why create the network manually?

Normally, Compose creates a network for each project.

By creating the network ourselves, we can tell both projects to use the same existing network rather than creating separate ones.

In Compose, we'll use:

```yaml
networks:
  app-testing:
    external: true
```

The `external: true` setting tells Compose that the network already exists and is managed outside that Compose project.

Compose will use it but will not create or manage its lifecycle.

This is similar to the way we'll reuse the existing database volume.

---

## 5. Move the database into its own Compose project

We'll now create a dedicated Compose file for MySQL.

Create or update:

`database/compose.yml`

```yaml
services:
  database:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: php_app
      MYSQL_USER: php_user
      MYSQL_PASSWORD: development_password
      MYSQL_ROOT_PASSWORD: development_root_password
    volumes:
      - db_data:/var/lib/mysql
      - ./init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
    networks:
      app-testing:
        aliases:
          - database

volumes:
  db_data:
    external: true
    name: php-docker-tutorial_db_data

networks:
  app-testing:
    external: true
```

Let's examine the important changes.

### The database has its own Compose file

The database is no longer defined in the application's Compose file.

We can manage it independently by running Compose commands from the `database` directory.

### The network is external

```yaml
networks:
  app-testing:
    external: true
```

This connects the database to the network we created earlier.

### The database has a network alias

```yaml
networks:
  app-testing:
    aliases:
      - database
```

The alias makes the database reachable as `database` by other containers on the shared network.

The service name is also `database`, but the explicit alias makes the intended network name clear.

### The volume is external

```yaml
volumes:
  db_data:
    external: true
    name: php-docker-tutorial_db_data
```

This is the most important part of the migration.

We are telling Compose to use an existing Docker volume with the exact name `php-docker-tutorial_db_data`.

The `db_data` name used in the service's volume mapping is a local reference within this Compose file. The `name` property specifies the actual Docker volume to use.

The `external: true` setting tells Compose that the volume already exists and must not be created or removed as a resource managed by this project.

**Do not change the volume's actual name unless you have verified that the replacement volume contains the data you want.**

### What about the initialisation script?

We still mount `init.sql` into MySQL's initialisation directory.

However, MySQL only runs these initialisation scripts when it initialises a new, empty data directory. Because we're reusing our existing volume, the script will not run again.

That is exactly what we want: the database already contains the data we created in the earlier tutorials.

### Start the database independently

From the `database` directory, run:

```bash
docker compose up -d
```

This starts the MySQL container without starting the PHP application.

Check its status:

```bash
docker compose ps
```

If the database doesn't start, inspect its logs:

```bash
docker compose logs database
```

You can also check which containers are connected to the shared network:

```bash
docker network inspect app-testing
```

The database container should now appear in the network's container list.

---

## 6. Update the application's Compose file

Now we need to remove the database service from the application's Compose file.

Update the root `compose.yml` to contain only the application:

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
    networks:
      - app-testing

networks:
  app-testing:
    external: true
```

Notice that the `database` service has disappeared.

We no longer need this:

```yaml
depends_on:
  - database
```

That setting was useful when both services belonged to the same Compose project. It instructed Compose to start the database service before the application.

Now that the database belongs to another project, the application project's Compose configuration cannot use `depends_on` to manage the database service.

Instead, we start the database project separately.

The application still has:

```yaml
DB_HOST: database
```

We haven't changed the PHP application's connection settings. The hostname works because the database has the `database` alias on the shared network.

This is a useful example of how we can change the infrastructure around an application without changing the application itself.

### Start the application

From the root project directory, run:

```bash
docker compose up -d --build
```

Check the status:

```bash
docker compose ps
```

You should see the application container, but not the database container. That's because this Compose project manages only the application.

The database is managed by the separate Compose project.

Now open:

```text
http://localhost:8080
```

Your existing messages should still appear.

If they do, you've successfully moved the database into its own Compose project while retaining its persistent data.

---

## 7. Prove that the lifecycles are independent

Let's test the main benefit of this architecture.

### Test A: Stop the application

From the root project directory, run:

```bash
docker compose stop
```

The application stops, but the database remains running.

Check the database from its own project:

```bash
cd database
docker compose ps
```

The database should still be running.

Return to the root directory and restart the application:

```bash
cd ..
docker compose start
```

Open the browser again. The application should connect to the database and display the existing messages.

### Test B: Remove the application container

From the root directory, run:

```bash
docker compose down
```

This removes the application container and its project-specific resources.

It does not stop or remove the database container because that container belongs to a different Compose project.

Check the database:

```bash
cd database
docker compose ps
```

It should still be running.

Now recreate the application:

```bash
cd ..
docker compose up -d --build
```

The application should reconnect to the existing database and display the same messages.

### Test C: Restart the database independently

We can also manage the database separately.

From the `database` directory:

```bash
docker compose restart database
```

This restarts the database container without recreating the application container.

The application may briefly be unable to connect while MySQL restarts. Depending on how the PHP application handles connection failures, you may need to refresh the browser after MySQL is ready.

The important point is that the database can be restarted independently.

### Test D: Verify the data survives container replacement

Finally, let's remove and recreate the database container itself.

First, ensure the application is no longer needed for this test. Then, from the `database` directory:

```bash
docker compose down
```

Because we declared the volume as external, Compose will not remove it.

Verify that the volume still exists:

```bash
docker volume ls
```

Now recreate the database:

```bash
docker compose up -d
```

Once MySQL has finished starting, start or restart the application if necessary:

```bash
cd ..
docker compose up -d
```

Open the application in your browser.

The messages should still be present.

This demonstrates the distinction between a container and a volume:

- The database container can be removed and recreated.
- The volume retains the database files.
- The new container uses the same volume and therefore sees the existing data.

---

## 8. Connecting a second application

We now have a reusable database project.

Imagine we have a second application that also needs to connect to this MySQL server.

Its Compose file would need to join the same network:

```yaml
services:
  app:
    build: .
    ports:
      - "127.0.0.1:8081:80"
    networks:
      - app-testing

networks:
  app-testing:
    external: true
```

The different host port avoids a conflict with the first application's port mapping.

The second application can connect using the same hostname:

```text
database
```

It can use a different database name and database account if required.

For example, the same MySQL server could host:

```text
php_app
ares2_test
reporting_test
```

These would be separate databases on one MySQL server.

We don't need a separate MySQL container for every application, although there may be reasons to use separate instances when applications require different MySQL versions or configurations.

### Important consideration: sharing data

Sharing a database server does not necessarily mean every application should use the same database or credentials.

If two applications need different schemas, they can use separate databases on the same MySQL server.

If they need the same underlying data, they can use the same database, provided their permissions and expected schema are compatible.

For a realistic test environment, it is worth thinking carefully about which applications should share databases and which should have their own test data.

---

## 9. An important limitation: startup order and readiness

Previously, `depends_on` gave us a convenient way to express that the application depends on the database service.

Now the database is managed separately, so Compose cannot automatically coordinate the startup of both projects through that dependency.

The database project should be started first:

```bash
cd database
docker compose up -d
```

Then start the application:

```bash
cd ..
docker compose up -d --build
```

However, starting the database container does not guarantee that MySQL is ready to accept connections immediately.

If the application attempts to connect too early, it may receive a connection error.

For this tutorial, restarting or refreshing the application after MySQL is ready is sufficient.

In a more mature environment, we could add a MySQL health check and configure a startup script or orchestration process to wait for readiness. Because these are independent Compose projects, that coordination would need to happen outside the simple `depends_on` relationship we used earlier.

---

## 10. Clean-up

You can stop and remove the application independently:

```bash
docker compose down
```

From the database directory, you can stop and remove the database container:

```bash
docker compose down
```

The database's external named volume remains in place.

The shared network also remains because both Compose projects treat it as an external resource.

To remove the shared network manually, first stop and remove all containers attached to it:

```bash
docker network rm app-testing
```

Docker will refuse to remove the network if containers are still connected to it.

**Do not remove the database volume unless you deliberately want to delete the persistent database data.** For this tutorial, we want to keep it.

---

## 11. Summary

In this tutorial, we've separated the database from the application and given each its own Compose project.

The key concepts are:

**Compose projects**

Each Compose project manages its own services and lifecycle. Removing one project's containers does not automatically remove containers managed by another project.

**Shared networks**

Containers in separate Compose projects can communicate when they join the same Docker network. A network alias gives the database a hostname that applications can use.

**External resources**

By declaring the network and volume as external, we tell Compose to use resources managed outside the individual project rather than creating new project-specific resources.

**Persistent storage**

The database volume retains its data even when the database container is removed and recreated.

**Service discovery**

The PHP application can continue connecting to `database` because the database has that network alias. The application doesn't need to know which Compose project manages the database.

The result is a more flexible testing environment: we can bring individual applications up and down independently while retaining a shared, persistent database server.

This is a useful pattern for testing legacy applications because it separates the application environment from the supporting services without requiring changes to the application itself.