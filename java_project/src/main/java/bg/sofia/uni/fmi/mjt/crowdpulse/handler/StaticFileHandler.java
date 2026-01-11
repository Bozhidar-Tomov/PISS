package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;

public class StaticFileHandler implements HttpHandler {
    private static final String PUBLIC_DIR = "public";

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        try {
            String uri = exchange.getRequestURI().getPath();
            // Remove leading slash to build relative resource path
            if (uri.startsWith("/")) {
                uri = uri.substring(1);
            }

            String resourcePath = PUBLIC_DIR + "/" + uri;
            java.net.URL url = getClass().getClassLoader().getResource(resourcePath);

            if (url != null) {
                // Basic MIME type detection by extension
                String mimeType = "application/octet-stream";
                if (uri.endsWith(".css"))
                    mimeType = "text/css";
                else if (uri.endsWith(".js"))
                    mimeType = "application/javascript";
                else if (uri.endsWith(".html"))
                    mimeType = "text/html";
                else if (uri.endsWith(".png"))
                    mimeType = "image/png";
                else if (uri.endsWith(".jpg") || uri.endsWith(".jpeg"))
                    mimeType = "image/jpeg";
                else if (uri.endsWith(".gif"))
                    mimeType = "image/gif";
                else if (uri.endsWith(".svg"))
                    mimeType = "image/svg+xml";
                else if (uri.endsWith(".wav"))
                    mimeType = "audio/wav";
                else if (uri.endsWith(".mp3"))
                    mimeType = "audio/mpeg";

                exchange.getResponseHeaders().set("Content-Type", mimeType);

                // For streams, we might not know size efficiently without reading,
                // but sendResponseHeaders with 0 uses chunked encoding or standard stream
                // behavior
                // Or we can read all bytes. For small files, readAllBytes is fine.
                // For larger, chunked is better. HttpExchange (0) means chunked.

                try (java.io.InputStream is = url.openStream()) {
                    byte[] bytes = is.readAllBytes();
                    exchange.sendResponseHeaders(200, bytes.length);
                    try (OutputStream os = exchange.getResponseBody()) {
                        os.write(bytes);
                    }
                }
            } else {
                String response = "404 Not Found";
                System.err.println("Static file not found: " + resourcePath);
                exchange.sendResponseHeaders(404, response.length());
                try (OutputStream os = exchange.getResponseBody()) {
                    os.write(response.getBytes());
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
            String response = "500 Internal Server Error";
            try {
                exchange.sendResponseHeaders(500, response.length());
                try (OutputStream os = exchange.getResponseBody()) {
                    os.write(response.getBytes());
                }
            } catch (IOException io) {
                // Ignore if we can't write response
                io.printStackTrace();
            }
        }
    }
}
