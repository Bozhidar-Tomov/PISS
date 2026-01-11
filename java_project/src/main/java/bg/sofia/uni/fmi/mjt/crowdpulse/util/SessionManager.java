package bg.sofia.uni.fmi.mjt.crowdpulse.util;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import com.sun.net.httpserver.HttpExchange;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

public class SessionManager {
    private static final Map<String, User> sessions = new ConcurrentHashMap<>();
    public static final String SESSION_COOKIE_NAME = "CROWD_PULSE_SESSION";

    public static String createSession(User user) {
        String sessionId = UUID.randomUUID().toString();
        sessions.put(sessionId, user);
        return sessionId;
    }

    public static User getUser(String sessionId) {
        return sessions.get(sessionId);
    }

    public static void invalidateSession(String sessionId) {
        sessions.remove(sessionId);
    }

    public static User getUserFromExchange(HttpExchange exchange) {
        String cookieHeader = exchange.getRequestHeaders().getFirst("Cookie");
        if (cookieHeader != null) {
            String[] cookies = cookieHeader.split(";");
            for (String cookie : cookies) {
                String[] parts = cookie.trim().split("=");
                if (parts.length == 2 && SESSION_COOKIE_NAME.equals(parts[0])) {
                    return getUser(parts[1]);
                }
            }
        }
        return null;
    }
}
