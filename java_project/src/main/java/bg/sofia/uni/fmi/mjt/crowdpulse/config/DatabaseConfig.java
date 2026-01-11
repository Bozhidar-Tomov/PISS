package bg.sofia.uni.fmi.mjt.crowdpulse.config;

import com.mongodb.client.MongoClient;
import com.mongodb.client.MongoClients;
import com.mongodb.client.MongoDatabase;

public class DatabaseConfig {
    private static final String DEFAULT_CONNECTION_STRING = "mongodb://localhost:27017";
    private static final String DB_NAME = "crowd_pulse";

    private static final String CONFIG_FILE = "application.properties";
    private static MongoClient mongoClient;
    private static MongoDatabase database;

    public static synchronized MongoDatabase getDatabase() {
        if (database == null) {
            String connectionString = loadConnectionString();
            try {
                mongoClient = MongoClients.create(connectionString);
                database = mongoClient.getDatabase(DB_NAME);
                System.out.println("Connected to MongoDB: " + DB_NAME);
            } catch (Exception e) {
                System.err.println("Failed to connect to MongoDB: " + e.getMessage());
                throw new RuntimeException("Database connection failed", e);
            }
        }
        return database;
    }

    private static String loadConnectionString() {
        try (java.io.InputStream input = DatabaseConfig.class.getClassLoader().getResourceAsStream(CONFIG_FILE)) {
            java.util.Properties prop = new java.util.Properties();
            if (input == null) {
                System.out.println("Sorry, unable to find " + CONFIG_FILE);
                return DEFAULT_CONNECTION_STRING;
            }
            prop.load(input);
            return prop.getProperty("mongodb.uri", DEFAULT_CONNECTION_STRING);
        } catch (java.io.IOException ex) {
            ex.printStackTrace();
            return DEFAULT_CONNECTION_STRING;
        }
    }

    public static synchronized void close() {
        if (mongoClient != null) {
            mongoClient.close();
            mongoClient = null;
            database = null;
        }
    }
}
