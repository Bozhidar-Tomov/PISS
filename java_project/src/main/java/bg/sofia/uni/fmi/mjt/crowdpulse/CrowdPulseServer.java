package bg.sofia.uni.fmi.mjt.crowdpulse;

import bg.sofia.uni.fmi.mjt.crowdpulse.config.DatabaseConfig;
import bg.sofia.uni.fmi.mjt.crowdpulse.handler.*;
import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.net.InetSocketAddress;

public class CrowdPulseServer {
    private static final int PORT = 8081;

    public static void main(String[] args) throws IOException {
        // Initialize DB connection
        try {
            DatabaseConfig.getDatabase();
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Failed to connect to DB, exiting.");
            return;
        }

        HttpServer server = HttpServer.create(new InetSocketAddress(PORT), 0);

        // Register handlers
        server.createContext("/css", new StaticFileHandler());
        server.createContext("/js", new StaticFileHandler());
        server.createContext("/media", new StaticFileHandler());
        server.createContext("/audio", new StaticFileHandler());

        server.createContext("/login", new AuthHandler());
        server.createContext("/register", new AuthHandler());
        server.createContext("/logout", new AuthHandler());

        server.createContext("/admin", new AdminHandler());
        server.createContext("/room", new RoomHandler());

        server.createContext("/sse", new SseHandler());
        server.createContext("/api", new ApiHandler());

        // Root redirect to login
        server.createContext("/", exchange -> {
            if (exchange.getRequestURI().getPath().equals("/")) {
                exchange.getResponseHeaders().set("Location", "/login");
                exchange.sendResponseHeaders(302, -1);
            } else {
                new StaticFileHandler().handle(exchange); // Fallback for other static files if mapped at root
            }
        });

        server.setExecutor(java.util.concurrent.Executors.newCachedThreadPool()); // Multi-threaded
        server.start();

        System.out.println("Crowd Pulse Server started on port " + PORT);
    }
}
